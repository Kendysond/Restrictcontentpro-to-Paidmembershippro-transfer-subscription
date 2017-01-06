<?php set_time_limit(0);
 
global $wpdb;
require_once('wp-blog-header.php');
require_once('wp-includes/registration.php');
require_once('wp-load.php');

function transfer($user_id,$olddate)
{
    global $pmproiufcsv_email, $wpdb;
    wp_cache_delete($user_id, 'users');
	$user = get_userdata($user_id);
	
	//look for a membership level and other information
	$membership_id = 1;//Set the membership level
	$membership_code_id = "";
	$membership_discount_code = "";
	$membership_initial_payment = "100.00";//New Membership Amount
	$membership_billing_amount = "100.00";//New Membership recurring amount
	$membership_cycle_number = 1;//New Membership Cycle
	$membership_cycle_period = 'Month';//New Membership Period
	$membership_billing_limit = 0;
	$membership_trial_amount = "0.00";
	$membership_trial_limit = "0";
	$membership_status = 'success';
	$membership_startdate = $olddate;
	
	$membership_enddate = date("Y-m-d", strtotime("+ " . $membership_cycle_number . " " . $membership_cycle_period, strtotime($membership_startdate)));

	$membership_timestamp = "";
		
	//fix date formats
	
	if(!empty($membership_timestamp))	
		$membership_timestamp = date("Y-m-d", strtotime($membership_timestamp, current_time('timestamp')));
	
		
	//change membership level
	if(!empty($membership_id))
	{
		$custom_level = array(
			'user_id' => $user_id,
			'membership_id' => $membership_id,
			'code_id' => $membership_code_id,
			'initial_payment' => $membership_initial_payment,
			'billing_amount' => $membership_billing_amount,
			'cycle_number' => $membership_cycle_number,
			'cycle_period' => $membership_cycle_period,
			'billing_limit' => $membership_billing_limit,
			'trial_amount' => $membership_trial_amount,
			'trial_limit' => $membership_trial_limit,
			'status' => $membership_status,
			'startdate' => $membership_startdate,
			'enddate' => $membership_enddate
		);
		// echo "<pre>";
		// print_r($custom_level);
		// die();	
		pmpro_changeMembershipLevel($custom_level, $user_id);
		
		//if membership was in the past make it inactive
		if($membership_status === "inactive" || (!empty($membership_enddate) && $membership_enddate !== "NULL" && strtotime($membership_enddate, current_time('timestamp')) < current_time('timestamp')))
		{			
			$sqlQuery = "UPDATE $wpdb->pmpro_memberships_users SET status = 'inactive' WHERE user_id = '" . $user_id . "' AND membership_id = '" . $membership_id . "'";		
			$wpdb->query($sqlQuery);
			$membership_in_the_past = true;
			
		}
		
		if($membership_status === "active" && (empty($membership_enddate) || $membership_enddate === "NULL" || strtotime($membership_enddate, current_time('timestamp')) >= current_time('timestamp')))
		{			
			$sqlQuery = $wpdb->prepare("UPDATE {$wpdb->pmpro_memberships_users} SET status = 'active' WHERE user_id = %d AND membership_id = %d", $user_id, $membership_id);		
			$wpdb->query($sqlQuery);
		

		}
	}
	
	//look for a subscription transaction id and gateway
	$membership_subscription_transaction_id = "Oldsubscription";
	$membership_payment_transaction_id ="Oldsubscription";
	$membership_affiliate_id = "";
	$membership_gateway = "Paypal";//Old membership gateway
		
	//add order so integration with gateway works
	if(
		!empty($membership_subscription_transaction_id) && !empty($membership_gateway) ||
		!empty($membership_timestamp) || !empty($membership_code_id)
	)
	{
		$order = new MemberOrder();
		$order->user_id = $user_id;
		$order->membership_id = $membership_id;
		$order->InitialPayment = $membership_initial_payment;		
		$order->payment_transaction_id = $membership_payment_transaction_id;
		$order->subscription_transaction_id = $membership_subscription_transaction_id;
		$order->affiliate_id = $membership_affiliate_id;
		$order->gateway = $membership_gateway;
		if(!empty($membership_in_the_past))
			$order->status = "cancelled";
		$order->saveOrder();
		//update timestamp of order?
		if(!empty($membership_timestamp))
		{
			$timestamp = strtotime($membership_timestamp, current_time('timestamp'));
			$order->updateTimeStamp(date("Y", $timestamp), date("m", $timestamp), date("d", $timestamp), date("H:i:s", $timestamp));
		}
	}
}
/// Loop through to get the subscriptions from the last 30 days.
// My table is called `sce_rcp_payments`, yours might be a different name. But it should be suffixed `rcp_payments`
$sql = "SELECT  * FROM sce_rcp_payments WHERE   date BETWEEN CURDATE() - INTERVAL 31 DAY AND CURDATE()";
$result = $wpdb->get_results($sql, OBJECT);

foreach ($result as $key => $obj) {
	transfer($obj->user_id,$obj->date);
}

// ss(17);
