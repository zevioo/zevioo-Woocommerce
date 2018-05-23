<?php
/*
Plugin Name: Zevioo.com
Plugin URI: 
Description: Send Order Details to Zevioo via API
Version: 1.1.1
Author: Zevioo
Author URI: https://zevioo.com
*/


/*  Copyright 2018  zevioo  

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
    
*/
add_action( 'admin_menu', 'admin_zevioo_menu');

add_action( 'woocommerce_checkout_order_processed', 'action_send_order_to_zevioo', 10, 3);
add_action( 'woocommerce_order_status_cancelled', 'action_cancel_order_in_zevioo' );

function action_send_order_to_zevioo($order_id) {
	global $post_type, $wp_query;
	$order = wc_get_order($order_id);
	
	$products_array  = array();
    $items = $order->get_items();
	foreach ($items as $item)
	{
		$_product = $order->get_product_from_item( $item );
		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $item['product_id'] ), 'single-post-thumbnail' );
		
		$EAN = $_product->get_sku() ? $_product->get_sku(): $item['product_id'];
		$product_item = array(
					'CD' => $item['product_id'],
					'EAN' => $EAN,
					'NM' => $item['name'],
					'IMG' => $image[0],
					'PRC' => $_product->get_price(),
					'QTY' => $item['qty']
		);
	
		$products_array[] = $product_item;
	}

	
	$last_name = get_post_meta( $order_id, '_billing_last_name', true );
	
	$orderData = array(
				'USR' => get_option('zevioo_username'),
				'PSW' => get_option('zevioo_password'),
				'OID' => $order_id,
				'PDT' => date('Y-m-d H:i:s'),
				'DDT' => '',
				'EML' => get_post_meta( $order_id, '_billing_email', true ),
				'FN' => get_post_meta( $order_id, '_billing_first_name', true ),
				'LN' => substr($last_name,0,1),
				'ITEMS' => $products_array
	);
	
	$service_new_order_url = 'https://api.zevioo.com/main.svc/custpurchase';
	$returnData = zevioo_post_request($service_new_order_url, $orderData);

	zevioo_write_log('New order: #' . $order_id . ', request: ' .  print_r($orderData, true) . ', result: ' . print_r($returnData, true));
}

function action_cancel_order_in_zevioo($order_id){
	$orderData = array(
					'USR' => get_option('zevioo_username'),
					'PSW' => get_option('zevioo_password'),
					'OID' => $order_id,
					'CDT' => date('Y-m-d H:i:s')
				);
	$order = wc_get_order($order_id);
    $items = $order->get_items();
	$service_cancel_order_url = 'https://api.zevioo.com/main.svc/cnlpurchase';	
    $returnData = zevioo_post_request($service_cancel_order_url, $orderData);
	zevioo_write_log('Cancel Order : #' . $order_id . ', request: ' .  print_r($orderData, true) . ', result: ' . print_r($returnData, true));
}

function zevioo_write_log($message){
	$fp = fopen(ABSPATH . 'wp-content/plugins/zevioo/debug.txt', 'a');
	fwrite($fp, $message);
	fclose($fp);
}

function zevioo_post_request($url, $postData){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $data = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
		$data = json_decode($data);
		return array('data'=>$data, 'http_status'=>$http_status);
}

function admin_zevioo_menu() {
	add_menu_page( 'Zevioo', 'Zevioo', 'manage_options', 'admin_zevioo', 'admin_zevioo_settings', '', 72 );
}

function admin_zevioo_settings(){
// Save the field values
	if ( isset( $_POST['fields_submitted'] ) && $_POST['fields_submitted'] == 'submitted' ) {
		foreach ( $_POST as $key => $value ) {
			if ( get_option( $key ) != $value ) {
				update_option( $key, $value );
			} else {
				add_option( $key, $value, '', 'no' );
			}
		}
	}
?>
<style>table td p {padding:0px !important;} table.dymocheck{width:100%;border:1px solid #ccc !important;text-align:center;margin:0 0 20px 0}.dymocheck tr th{border-bottom:1px solid #ccc !important;background:#ccc;}</style>
<div class="wrap">
	<div id="icon-options-general" class="icon32"></div>
	<h2>Zevioo settings</h2>
	
	<?php if ( isset( $_POST['fields_submitted'] ) && $_POST['fields_submitted'] == 'submitted' ) { ?>
	<div id="message" class="updated fade"><p><strong>Your settings have been saved.</strong></p></div>
	<?php } 
	?>
	
	<div id="content">
		<form method="post" action="" id="settings">
			<input type="hidden" name="fields_submitted" value="submitted">
			<div id="poststuff">
				<div style="float:left; width:80%; padding-right:1%;">
					<div class="postbox">
						<h3>Account Settings</h3>
						<div class="inside dymo-settings">
							<table class="form-table">
								<tr>
    								<th>
    									<label for="zevioo_username"><b>Zevioo Username</b></label>
    								</th>
    								<td>
										<input type="text" name="zevioo_username" id="zevioo_username" value="<?php echo get_option( 'zevioo_username' );?>" size="40"/>
    								</td>
    							</tr>
								<tr>
    								<th>
    									<label for="zevioo_password"><b>Zevioo Password</b></label>
    								</th>
    								<td>
										<input type="text" name="zevioo_password" id="zevioo_password" value="<?php echo get_option( 'zevioo_password' );?>" size="40"/>
    								</td>
    							</tr>
								<tr>
									<td colspan=2>
										<p class="submit"><input type="submit" name="Submit" class="button-primary" value="Save Changes" /></p>
									</td>
								</tr>
							</table>
						</div>
						
					</div>

				</div>
			</div>
		</form>
	</div>
</div>
<?php 
}
?>