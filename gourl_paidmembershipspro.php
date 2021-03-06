<?php
/*
Plugin Name: 		GoUrl Paid Memberships Pro - Bitcoin Payment Gateway Addon
Plugin URI: 		https://gourl.io/bitcoin-payments-paid-memberships-pro.html
Description: 		Provides a <a href="https://gourl.io">GoUrl.io</a> Bitcoin/Altcoin Payment Gateway for <a href="https://wordpress.org/plugins/paid-memberships-pro/">Paid Memberships Pro 1.8+</a>. Direct Integration on your website, no external payment pages opens (as other payment gateways offer). Accept Bitcoin, Litecoin, Paycoin, Dogecoin, Dash, Speedcoin, Reddcoin, Potcoin, Feathercoin, Vertcoin, Vericoin, Peercoin payments online. You will see the bitcoin/altcoin payment statistics in one common table on your website. No Chargebacks, Global, Secure. All in automatic mode.
Version: 			1.1.1
Author: 			GoUrl.io
Author URI: 		https://gourl.io
License: 			GPLv2
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: 	https://github.com/cryptoapi/Bitcoin-Gateway-Paid-Memberships-Pro
*/


if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly

if (!function_exists('gourl_pmp_gateway_load'))
{
	// localisation
	add_action( 'plugins_loaded', 'gourl_pmp_load_textdomain' );
		
	// gateway load
	add_action( 'plugins_loaded', 'gourl_pmp_gateway_load', 20);
	
	DEFINE('GOURLPMP', "gourl-paidmembershipspro");
	
	
	
	function gourl_pmp_load_textdomain()
	{
		load_plugin_textdomain( GOURLPMP, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	
	
	function gourl_pmp_gateway_load()
	{

		// paid memberships pro required
		if (!class_exists('PMProGateway')) return;

		// load classes init method
		add_action('init', array('PMProGateway_gourl', 'init'));
		
		// add cryptocurrencies
		add_filter('pmpro_currencies', array('PMProGateway_gourl', 'pmpro_currencies'), 10, 1);
		
		// order log
		add_action('pmpro_after_order_settings', array('PMProGateway_gourl', 'pmpro_after_order_settings'));
		
		// custom confirmation page
		add_filter('pmpro_pages_shortcode_confirmation', array('PMProGateway_gourl', 'pmpro_pages_shortcode_confirmation'), 20, 1);
		
		// plugin links
		add_filter('plugin_action_links', array('PMProGateway_gourl', 'plugin_action_links'), 10, 2 );

		
		
		/*
		 *  1.
		*/
		class PMProGateway_gourl extends PMProGateway
		{
		
			/**
			 * 1.1
			 */
			public function PMProGateway_gourl($gateway = NULL)
			{
				$this->gateway = $gateway;
				return $this->gateway;
			}
		
			/**
			 * 1.2 Run on WP init
			 */
			public static function init()
			{
				//make sure Pay by Bitcoin/Altcoin is a gateway option
				add_filter('pmpro_gateways', array('PMProGateway_gourl', 'pmpro_gateways'));
		
				//add fields to payment settings
				add_filter('pmpro_payment_options', array('PMProGateway_gourl', 'pmpro_payment_options'));
				add_filter('pmpro_payment_option_fields', array('PMProGateway_gourl', 'pmpro_payment_option_fields'), 10, 2);
					
				//code to add at checkout
				$gateway = pmpro_getGateway();
				if($gateway == "gourl")
				{
					add_filter('pmpro_include_billing_address_fields', '__return_false');
					add_filter('pmpro_include_payment_information_fields', '__return_false');
					add_filter('pmpro_required_billing_fields', array('PMProGateway_gourl', 'pmpro_required_billing_fields'));
					add_filter('pmpro_checkout_before_change_membership_level', array('PMProGateway_gourl', 'pmpro_checkout_before_change_membership_level'), 10, 2);
				}
			}
		
			
			/**
			 * 1.3
			*/
			public static function plugin_action_links($links, $file)
			{
				static $this_plugin;
			
				if (false === isset($this_plugin) || true === empty($this_plugin)) {
					$this_plugin = plugin_basename(__FILE__);
				}
			
				if ($file == $this_plugin) {
					$settings_link = '<a href="'.admin_url('admin.php?page=pmpro-paymentsettings').'">'.__( 'Settings', GOURLPMP ).'</a>';
					array_unshift($links, $settings_link);
				}
			
				return $links;
			}
			
				
			/**
			 * 1.4 Make sure Gourl is in the gateways list
			 */
			public static function pmpro_gateways($gateways)
			{
				if(empty($gateways['gourl']))
				$gateways = array_slice($gateways, 0, 1) + array("gourl" => __('GoUrl Bitcoin/Altcoins', GOURLPMP)) + array_slice($gateways, 1);
		
				return $gateways;
			}
		
			/**
			 * 1.5 Get a list of payment options that the gourl gateway needs/supports.
			 */
			public static function getGatewayOptions()
			{
				$options = array(
						'gourl_defcoin',
						'gourl_deflang',
						'gourl_emultiplier',
						'gourl_iconwidth',
						'currency'
				);
		
				return $options;
			}
		
			/**
			 * 1.6 Set payment options for payment settings page.
			 */
			public static function pmpro_payment_options($options)
			{
				//get stripe options
				$gourl_options = self::getGatewayOptions();
		
				//merge with others.
				$options = array_merge($gourl_options, $options);
		
				return $options;
			}
		
			/**
			 * 1.7 Add cryptocurrencies
			 */
			public static function pmpro_currencies($currencies)
			{
				global $gourl;
					
				if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
				{
					$arr = $gourl->coin_names();
						
					foreach ($arr as $k => $v)
						$currencies[$k] = __( "Cryptocurrency", GOURLPMP ) . " - " . __( ucfirst($v), GOURLPMP );
				}
				
				__( 'Bitcoin', GOURLPMP );  // use in translation
					
				return $currencies;
			}
		
			/**
			 * 1.8 Display fields for Gourl options.
			 */
			public static function pmpro_payment_option_fields($options, $gateway)
			{
				global $gourl;
					
				$payments 		= array();
				$coin_names 	= array();
				$languages 		= array();
				$mainplugin_url = admin_url("plugin-install.php?tab=search&type=term&s=GoUrl+Bitcoin+Payment+Gateway+Downloads");
		
				$description  	= "<a target='_blank' href='https://gourl.io/'><img border='0' style='float:left; margin-right:15px' src='https://gourl.io/images/gourlpayments.png'></a>";
				$description  .= "<a target='_blank' href='https://gourl.io/bitcoin-payments-paid-memberships-pro.html'>".__( 'Plugin Homepage', GOURLPMP )."</a> &#160;&amp;&#160; <a target='_blank' href='https://gourl.io/bitcoin-payments-paid-memberships-pro.html#screenshot'>".__( 'screenshots', GOURLPMP )." &#187;</a><br>";
				$description  .= "<a target='_blank' href='https://github.com/cryptoapi/Bitcoin-Gateway-Paid-Memberships-Pro'>".__( 'Plugin on Github - 100% Free Open Source', GOURLPMP )." &#187;</a><br><br>";
				
				if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
				{
					if (true === version_compare(GOURL_VERSION, '1.3.2', '<'))
					{
						$description .= '<div style="background:#fff;border:1px solid #f77676;padding:7px"><p><b>' .sprintf(__( "Your GoUrl Bitcoin Gateway <a href='%s'>Main Plugin</a> version is too old. Requires 1.3.2 or higher version. Please <a href='%s'>update</a> to latest version.", GOURLPMP ), GOURL_ADMIN.GOURL, $mainplugin_url)."</b> &#160; &#160; &#160; &#160; " .
										__( 'Information', GOURLPMP ) . ": &#160; <a href='https://gourl.io/bitcoin-wordpress-plugin.html'>".__( 'Main Plugin Homepage', GOURLPMP )."</a> &#160; &#160; &#160; " .
										"<a href='https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/'>".__( 'WordPress.org Plugin Page', GOURLPMP )."</a></p></div><br>";
					}
					elseif (true === version_compare(PMPRO_VERSION, '1.8.4', '<'))
					{
						$description .= '<div style="background:#fff;border:1px solid #f77676;padding:7px"><p><b>' .sprintf(__( "Your PaidMembershipsPro version is too old. The GoUrl payment plugin requires PaidMembershipsPro 1.8.4 or higher to function. Please update to <a href='%s'>latest version</a>.", GOURLPMP ), admin_url('plugin-install.php?tab=search&type=term&s=paidmembershipspro+affiliates')).'</b></p></div><br>';
					}
					else
					{
						$payments 			= $gourl->payments(); 		// Activated Payments
						$coin_names			= $gourl->coin_names(); 	// All Coins
						$languages			= $gourl->languages(); 		// All Languages
					}
						
					$coins 	= implode(", ", $payments);
					$url	= GOURL_ADMIN.GOURL."settings";
					$url2	= GOURL_ADMIN.GOURL."payments&s=pmpro";
					$url3	= GOURL_ADMIN.GOURL;
					$text 	= ($coins) ? $coins : __( '- Please setup -', GOURLPMP );
				}
				else
				{
					$coins 	= "";
					$url	= $mainplugin_url;
					$url2	= $url;
					$url3	= $url;
					$text 	= '<b>'.__( 'Please install GoUrl Bitcoin Gateway WP Plugin', GOURLPMP ).' &#187;</b>';
						
					$description .= '<div style="background:#fff;border:1px solid #f77676;padding:7px;color:#444"><p><b>' .
							sprintf(__( "You need to install GoUrl Bitcoin Gateway Main Plugin also. Go to - <a href='%s'>Automatic installation</a> or <a href='%s'>Manual</a>.", GOURLPMP ), $mainplugin_url, "https://gourl.io/bitcoin-wordpress-plugin.html") . "</b> &#160; &#160; &#160; &#160; " .
							__( 'Information', GOURLPMP ) . ": &#160; &#160;<a href='https://gourl.io/bitcoin-wordpress-plugin.html'>".__( 'Main Plugin Homepage', GOURLPMP )."</a> &#160; &#160; &#160; <a href='https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/'>" .
							__( 'WordPress.org Plugin Page', GOURLPMP ) . "</a></p></div><br>";
						
				}
		
				$description  .= "<b>" . __( "Secure payments with virtual currency. <a target='_blank' href='https://bitcoin.org/'>What is Bitcoin?</a>", GOURLPMP ) . '</b><br>';
				$description  .= sprintf(__( 'Accept %s payments online in PaidMembershipsPro.', GOURLPMP ), __( ucwords(implode(", ", $coin_names)), GOURLPMP )).'<br>';
				if (class_exists('gourlclass')) $description .= sprintf(__( "If you use multiple websites online, please create separate <a target='_blank' href='%s'>GoUrl Payment Box</a> (with unique payment box public/private keys) for each of your websites. Do not use the same GoUrl Payment Box with the same public/private keys on your different websites.", GOURLPMP ), "https://gourl.io/editrecord/coin_boxes/0") . '<br><br>';

				
				$tr = '<tr class="gateway gateway_gourl"'.($gateway!="gourl"?' style="display: none;"':'').'>';
					
				// a
				$tmp  = '<tr class="pmpro_settings_divider gateway gateway_gourl"'.($gateway!="gourl"?' style="display: none;"':'').'>';
				$tmp .= '<td colspan="2">'.__('Gourl Bitcoin/Altcoin Settings', GOURLPMP).'</td>';
				$tmp .= "</tr>";
					
					
				// b
				$tmp .= $tr;
				$tmp .= '<td colspan="2"><div style="font-size:13px;line-height:22px">' . $description . '</div></td>';
				$tmp .= "</tr>";
					
					
				// c
				$defcoin = $options["gourl_defcoin"];
				if (!in_array($defcoin, array_keys($payments))) $defcoin = current(array_keys($payments));
					
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_defcoin">'.__( 'PaymentBox Default Coin', GOURLPMP ).'</label></th>
					<td><select name="gourl_defcoin" id="gourl_defcoin">';
				foreach ($payments as $k => $v) $tmp .= "<option value='".$k."'".self::sel($k, $defcoin).">".$v."</option>";
				$tmp .= "</select>";
				$tmp .= '<p class="description">'.sprintf(__( "Default Coin in Crypto Payment Box. Activated Payments : <a href='%s'>%s</a>", GOURLPMP), $url, $text)."</p></td>";
				
				$tmp .= "</tr>";
					
					
				// d
				$deflang = $options["gourl_deflang"];
				if (!in_array($deflang, array_keys($languages))) $deflang = current(array_keys($languages));
		
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_deflang">'.__( 'PaymentBox Language', GOURLPMP ).'</label></th>
					<td><select name="gourl_deflang" id="gourl_deflang">';
				foreach ($languages as $k => $v) $tmp .= "<option value='".$k."'".self::sel($k, $deflang).">".$v."</option>";
				$tmp .= "</select>";
				$tmp .= '<p class="description">'.__("Default Crypto Payment Box Localisation", GOURLPMP)."</p></td>";
				$tmp .= "</tr>";
					
					
				// e
				$emultiplier = str_replace("%", "", $options["gourl_emultiplier"]);
				if (!$emultiplier || !is_numeric($emultiplier) || $emultiplier <= 0) $emultiplier = "1.00";
					
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_emultiplier">'.__( 'Exchange Rate Multiplier', GOURLPMP ).'</label></th>
					<td><input type="text" value="'.$emultiplier.'" name="gourl_emultiplier" id="gourl_emultiplier">';
				$tmp .= '<p class="description">'.__('The system uses the multiplier rate with today LIVE cryptocurrency exchange rates (which are updated every 30 minutes) when the transaction is calculating from a fiat currency (e.g. USD, EUR, etc) to cryptocurrency. <br> Example: <b>1.05</b> - will add an extra 5% to the total price in bitcoin/altcoins, <b>0.85</b> - will be a 15% discount for the price in bitcoin/altcoins. Default: 1.00', GOURLPMP )."</p></td>";
				$tmp .= "</tr>";
					
					
				// f
				$iconwidth = str_replace("px", "", $options["gourl_iconwidth"]);
				if (!$iconwidth || !is_numeric($iconwidth) || $iconwidth < 30 || $iconwidth > 250) $iconwidth = 60;
				$iconwidth = $iconwidth . "px";
					
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_iconwidth">'.__( 'Icons Size', GOURLPMP ).'</label></th>
					<td><input type="text" value="'.$iconwidth.'" name="gourl_iconwidth" id="gourl_iconwidth">';
				$tmp .= '<p class="description">'.__( "Cryptocoin icons size in 'Select Payment Method' that the customer will see on your checkout. Default 60px. Allowed: 30..250px", GOURLPMP )."</p></td>";
				$tmp .= "</tr>";
					
					
				// g
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_boxstyle">'.__( 'PaymentBox Style', GOURLPMP ).'</label></th>
					<td>'.sprintf(__( "Payment Box <a href='%s'>sizes</a> and border <a href='%s'>shadow</a> you can change <a href='%s'>here &#187;</a>", GOURLPMP ), "https://gourl.io/images/global/sizes.png", "https://gourl.io/images/global/styles.png", $url."#gourlvericoinprivate_key")."</td>";
				$tmp .= "</tr>";
					
					
				// h
				$tmp .= $tr.'<th scope="row" valign="top"><label for="gourl_lang">'.__( 'Languages', GOURLPMP ).'</label></th>
					<td>'.sprintf(__( "If you want to use GoUrl PaidMembershipsPro Bitcoin Gateway plugin in a language other than English, see the page <a href='%s'>Languages and Translations</a>", GOURLPMP ), "https://gourl.io/languages.html")."</td>";
				$tmp .= "</tr>";
				
						
				echo $tmp;
					
				return;
			}
		
		
		
			/**
			 * 1.9 Remove required billing fields
			 */
			public static function pmpro_required_billing_fields($fields)
			{
				unset($fields['bfirstname']);
				unset($fields['blastname']);
				unset($fields['baddress1']);
				unset($fields['bcity']);
				unset($fields['bstate']);
				unset($fields['bzipcode']);
				unset($fields['bphone']);
				unset($fields['bemail']);
				unset($fields['bcountry']);
				unset($fields['CardType']);
				unset($fields['AccountNumber']);
				unset($fields['ExpirationMonth']);
				unset($fields['ExpirationYear']);
				unset($fields['CVV']);
					
				return $fields;
			}
		
		
		
			/**
			 * 1.10 Redirect to bitcoin/altcoin payment page
			 */
			public static function pmpro_checkout_before_change_membership_level($user_id, $order)
			{
				if (!$order || $order->gateway != "gourl") return true;
					
				// check for previous pending orders
				$morder = new MemberOrder();
				$morder->getLastMemberOrder(get_current_user_id(), apply_filters("pmpro_confirmation_order_status", array("pending")));
				
				if ($morder->gateway != "gourl" || $morder->subtotal != $order->subtotal || $morder->membership_id != $order->membership_id || strtotime($order->ProfileStartDate) < (strtotime("now") - 48*60*60) || 
					(isset($order->membership_level->expiration_number) && $order->membership_level->expiration_number > 0 && $order->membership_level->expiration_period && ($order->membership_level->expiration_number." ".$order->membership_level->expiration_period) != $morder->subscription_transaction_id))
				{
					$order->payment_type 	= __('GoUrl Bitcoin/Altcoin', GOURLPMP);
					$order->gateway 	 	= "gourl";
					$order->user_id 	 	= get_current_user_id();
					$order->status 	 	 	= "pending";
					if ($order->membership_level->expiration_number > 0 && $order->membership_level->expiration_period) $order->subscription_transaction_id = $order->membership_level->expiration_number." ".$order->membership_level->expiration_period;
					$order->saveOrder();
						
					$user = (!get_current_user_id()) ? __('Guest', GOURLPMP) : "<a href='".admin_url("user-edit.php?user_id=".get_current_user_id())."'>user".get_current_user_id()."</a>";
		
					self::add_order_note($order->id, sprintf(__('Order Created by <a>%s<br>Awaiting Cryptocurrency Payment ...', GOURLPMP ), $user));
				}

				wp_redirect(pmpro_url("confirmation"));
				die();
		
				return true;
			}
		
		
		
			/**
			 * 1.11 Custom confirmation page
			 *
			 */
			public static function pmpro_pages_shortcode_confirmation($content)
			{
				global $wpdb, $current_user, $pmpro_invoice, $pmpro_currency;
					
				if (empty($pmpro_invoice))
				{
					$morder = new MemberOrder();
					$morder->getLastMemberOrder(get_current_user_id(), apply_filters("pmpro_confirmation_order_status", array("pending", "success")));
					if (!empty($morder) && $morder->gateway == "gourl") $pmpro_invoice = $morder;
				}
					
				if (!empty($pmpro_invoice) && $pmpro_invoice->gateway == "gourl" && isset($pmpro_invoice->total) && $pmpro_invoice->total > 0)
				{
					$levelName = $wpdb->get_var("SELECT name FROM $wpdb->pmpro_membership_levels WHERE id = '" . $pmpro_invoice->membership_id . "' LIMIT 1");
		
					$content = "<ul>
							<li><strong>".__('Account', GOURLPMP).":</strong> ".$current_user->display_name." (".$current_user->user_email.")</li>
							<li><strong>".__('Order', GOURLPMP).":</strong> ".$pmpro_invoice->code."</li>
							<li><strong>".__('Membership Level', GOURLPMP).":</strong> ".$levelName."</li>
							<li><strong>".__('Amount', GOURLPMP).":</strong> ".$pmpro_invoice->total." ".$pmpro_currency."</li>
						  </ul>";
		
					$content .= self::pmpro_gourl_cryptocoin_payment($pmpro_invoice);
				}
		
		
				return $content;
		
			}
		
		
			/**
			 *  1.12. GoUrl Payment Box
				*/
			public static function pmpro_gourl_cryptocoin_payment ($order)
			{
				global $gourl, $pmpro_currency, $current_user, $wpdb;
		
				$tmp = "";
		
				// Initialize
				// ------------------------
				if (class_exists('gourlclass') && defined('GOURL') && is_object($gourl))
				{
					$payments 		= $gourl->payments(); 		// Activated Payments
					$coin_names		= $gourl->coin_names(); 	// All Coins
					$languages		= $gourl->languages(); 		// All Languages
				}
				else
				{
					$payments 		= array();
					$coin_names 	= array();
					$languages 		= array();
				}
		
				$defcoin = pmpro_getOption("gourl_defcoin");
				if (!in_array($defcoin, array_keys($payments))) $defcoin = current(array_keys($payments));
		
				$deflang = pmpro_getOption("gourl_deflang");
				if (!in_array($deflang, array_keys($languages))) $deflang = current(array_keys($languages));
		
				$emultiplier = str_replace("%", "", pmpro_getOption("gourl_emultiplier"));
				if (!$emultiplier || !is_numeric($emultiplier) || $emultiplier <= 0) $emultiplier = "1.00";
		
				$iconwidth = str_replace("px", "", pmpro_getOption("gourl_iconwidth"));
				if (!$iconwidth || !is_numeric($iconwidth) || $iconwidth < 30 || $iconwidth > 250) $iconwidth = 60;
				$iconwidth = $iconwidth . "px";
		
		
		
				// Current Order
				// -----------------
				$order_id 			= $order->id;
				$order_total		= $order->total;
				$order_currency		= $pmpro_currency;
				$order_user_id		= $order->user_id;
		
		
		
				// Security
				// -------------
				if (!$order_id || !$order)
				{
					$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
					$tmp .= "<div class='pmpro_message pmpro_error'>".sprintf(__( 'The GoUrl payment plugin was called to process a payment but could not retrieve the order details for orderID %s. Cannot continue!', GOURLPMP ), $order_id)."</div>";
				}
				elseif ($order->gateway != "gourl" || ($order_user_id && $order_user_id != get_current_user_id()))
				{ 
					return false;
				}
				elseif (!class_exists('gourlclass') || !defined('GOURL') || !is_object($gourl))
				{
					$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
					$tmp .= "<div class='pmpro_message pmpro_error'>".sprintf(__( "Please try a different payment method. Admin need to install and activate wordpress plugin <a href='%s'>GoUrl Bitcoin Gateway for Wordpress</a> to accept Bitcoin/Altcoin Payments online.", GOURLPMP ), "https://gourl.io/bitcoin-wordpress-plugin.html")."</div>";
				}
				elseif (!$payments || !$defcoin || true === version_compare(PMPRO_VERSION, '1.8.4', '<') || true === version_compare(GOURL_VERSION, '1.3', '<') ||
						(array_key_exists($order_currency, $coin_names) && !array_key_exists($order_currency, $payments)))
				{
					$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
					$tmp .=  "<div class='pmpro_message pmpro_error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try a different payment method or contact us if you need assistance (GoUrl Bitcoin Plugin not configured / %s not activated)', GOURLPMP ),(!$payments || !$defcoin || !isset($coin_names[$order_currency])?__("Cryptocurrency", GOURLPMP):$coin_names[$order_currency]))."</div>";
				}
				else
				{
					$plugin			= "gourlpmpro";
					$amount 		= $order_total;
					$currency 		= $order_currency;
					$orderID		= "order" . $order_id;
					$userID			= $order_user_id;
					$period			= "NOEXPIRY";
					$language		= $deflang;
					$coin 			= $coin_names[$defcoin];
					$affiliate_key 	= "gourl";
					$crypto			= array_key_exists($currency, $coin_names);
		
					if (!$userID) $userID = "guest"; // allow guests to make payments
		
		
					if (!$userID)
					{
						$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
						$tmp .= "<div align='center'><a href='".wp_login_url(get_permalink())."'>
					<img style='border:none;box-shadow:none;' title='".__('You need first to login or register on the website to make Bitcoin/Altcoin Payments', GOURLPMP )."' vspace='10'
					src='".$gourl->box_image()."' border='0'></a></div>";
					}
					elseif ($amount <= 0)
					{
						$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
						$tmp .= "<div class='pmpro_message pmpro_error'>". sprintf(__( "This order's amount is '%s' - it cannot be paid for. Please contact us if you need assistance.", GOURLPMP ), $amount ." " . $currency)."</div>";
					}
					else
					{
		
						// Exchange (optional)
						// --------------------
						if ($currency != "USD" && !$crypto)
						{
							$amount = gourl_convert_currency($currency, "USD", $amount);
		
							if ($amount <= 0)
							{
								$tmp .= '<h2>' . __( 'Information', GOURLPMP ) . '</h2>' . PHP_EOL;
								$tmp .= "<div class='pmpro_message pmpro_error'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try later or use a different payment method. Cannot receive exchange rates for %s/USD from Google Finance', GOURLPMP ), $currency)."</div>";
							}
							else $currency = "USD";
						}
		
						if (!$crypto) $amount = $amount * $emultiplier;
		
		
						// Payment Box
						// ------------------
						if ($amount > 0)
						{
							// crypto payment gateway
							$result = $gourl->cryptopayments ($plugin, $amount, $currency, $orderID, $period, $language, $coin, $affiliate_key, $userID, $iconwidth);
		
							if (!$result["is_paid"]) $tmp .= '<br><h3>' . __( 'Pay Now -', GOURLPMP ) . '</h3>' . PHP_EOL;
		
							if ($result["error"]) $tmp .= "<div class='pmpro_message pmpro_error'>".__( "Sorry, but there was an error processing your order. Please try a different payment method.", GOURLPMP )."<br/>".$result["error"]."</div>";
							else
							{
								// display payment box or successful payment result
								$tmp .= $result["html_payment_box"];
		
								// payment received
								if ($result["is_paid"])
								{
									$tmp .= "<div align='center'>" . sprintf(__('Thank you for your membership to %s.<br>Your %s membership is now active.', GOURLPMP), get_bloginfo("name"), $current_user->membership_level->name) . "</div>";
									$tmp .= "<br><br><div align='center'><a href=".pmpro_url("account").">".__('View Your Membership Account', GOURLPMP)." &rarr;</a>";
								}
							}
						}
					}
				}
		
				$tmp .= "<br><br>";
		
				return $tmp;
			}
		
		
		
		
		
			/**
			 * 1.13 Process checkout.
			 *
			 */
			function process(&$order)
			{
		
				return true;
			}
		
		
		
		
			/**
			 * 1.14 Show payment log on order details page
			 */
			public static function pmpro_after_order_settings($order)
			{
				if (!empty($order) && $order->gateway == "gourl")
				{
					$data = self::display_order_notes();
		
					if ($data)
					{
						$tmp  = '<tr><th scope="row" valign="top"></th>';
						$tmp .= '<td>';
						$tmp .= $data;
						$tmp .= '</td>';
						$tmp .= '</tr>';
		
						echo $tmp;
					}
				}
		
				return true;
			}
		
		
			/**
			 * 1.15 Save payment log
			 */
			public static function add_order_note($order_id, $notes)
			{
				$id	= GOURLPMP."_".$order_id."_gourl_log";
				$dt = current_time("mysql", 0);
		
				$arr = get_option($id);
				if (!$arr) $arr = array();
				$arr[] = "<tr><th style='padding-top:15px' valign='top'>" . $dt . "</th><td>" . $notes . "</td></tr>";
				update_option($id, $arr);
		
				return true;
			}
		
		
			/**
			 * 1.16 Display payment log
			 */
			public static function display_order_notes()
			{
				$tmp = "";
				if (is_admin() && isset($_GET["order"]) && is_numeric($_GET["order"]) && isset($_GET["page"]) && $_GET["page"] == "pmpro-orders")
				{
					$order_id = $_GET["order"];
						
					$data = get_option(GOURLPMP."_".$order_id."_gourl_log");
		
					if ($data)
					{
						$tmp  = "<br><h3>". __("Payment Log", GOURLPMP)." -</h3>";
						$tmp .= "<table>" . implode("\n", $data) . "</table>";
					}
				}
		
				return $tmp;
			}
		
		
			/**
			 * 1.17
			 */
			public static function sel($val1, $val2)
			{
				$tmp = ((is_array($val1) && in_array($val2, $val1)) || strval($val1) == strval($val2)) ? ' selected="selected"' : '';
		
				return $tmp;
			}
		
		}
		// end class
		
		
		
		
		
		
		
		/*
		*  2. Instant Payment Notification Function - pluginname."_gourlcallback"    
		*
		*  This function will appear every time by GoUrl Bitcoin Gateway when a new payment from any user is received successfully.
		*  Function gets user_ID - user who made payment, current order_ID (the same value as you provided to bitcoin payment gateway),
		*  payment details as array and box status.
		*
		*  The function will automatically appear for each new payment usually two times :
		*  a) when a new payment is received, with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 0
		*  b) and a second time when existing payment is confirmed (6+ confirmations) with values: $box_status = cryptobox_updated, $payment_details[is_confirmed] = 1.
		*
		*  But sometimes if the payment notification is delayed for 20-30min, the payment/transaction will already be confirmed and the function will
		*  appear once with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 1
		*
		*  Payment_details example - https://gourl.io/images/plugin2.png
		*  Read more - https://gourl.io/affiliates.html#wordpress
		*/
		function gourlpmpro_gourlcallback ($user_id, $order_id, $payment_details, $box_status)
		{
			global $wpdb;
		
			if (!in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;
		
			if (strpos($order_id, "order") === 0) $order_id = intval(substr($order_id, 5)); else return false;
		
			if (!$user_id || $payment_details["status"] != "payment_received") return false;
		
		
			// Initialize
			$coinName 	= ucfirst($payment_details["coinname"]);
			$amount		= $payment_details["amount"] . " " . $payment_details["coinlabel"] . "&#160; ( $" . $payment_details["amountusd"] . " )";
			$payID		= $payment_details["paymentID"];
			$trID		= $payment_details["tx"];
			$confirmed	= ($payment_details["is_confirmed"]) ? __('Yes', GOURLPMP) : __('No', GOURLPMP);
		
		
			// New Payment Received
			if ($box_status == "cryptobox_newrecord")
			{
				PMProGateway_gourl::add_order_note($order_id, sprintf(__("<b>%s</b> payment received<br>%s<br>Payment id <a href='%s'>%s</a>. Awaiting network confirmation...", GOURLPMP), $coinName, $amount, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID));
			}
		
		
			// Existing Payment confirmed (6+ confirmations)
			if ($payment_details["is_confirmed"])
			{
				PMProGateway_gourl::add_order_note($order_id, sprintf(__("%s Payment id <a href='%s'>%s</a> Confirmed", GOURLPMP), $coinName, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID));
			}
		
		
			// Update User Membership
			$order = new MemberOrder();
			$order->getMemberOrderByID($order_id);
		
			if (!empty($order) && $order->gateway == "gourl" && $order->status != "success")
			{
				$pmpro_level = $wpdb->get_row("SELECT * FROM $wpdb->pmpro_membership_levels WHERE id = '" . (int)$order->membership_id . "' LIMIT 1");
					
				$startdate = apply_filters("pmpro_checkout_start_date", "'" . current_time("mysql") . "'", $user_id, $pmpro_level);
				if (strlen($order->subscription_transaction_id) > 3) 
				{
					$enddate = "'" . date("Y-m-d", strtotime("+ " . $order->subscription_transaction_id, current_time("timestamp"))) . "'";
				}
				elseif (!empty($pmpro_level->expiration_number)) {
					$enddate = "'" . date("Y-m-d", strtotime("+ " . $pmpro_level->expiration_number . " " . $pmpro_level->expiration_period, current_time("timestamp"))) . "'";
				} else {
					$enddate = "NULL";
				}
					
				$custom_level = array(
						'user_id' 			=> $user_id,
						'membership_id' 	=> $pmpro_level->id,
						'code_id' 			=> '',
						'initial_payment' 	=> $pmpro_level->initial_payment,
						'billing_amount' 	=> $pmpro_level->billing_amount,
						'cycle_number' 		=> $pmpro_level->cycle_number,
						'cycle_period' 		=> $pmpro_level->cycle_period,
						'billing_limit' 	=> $pmpro_level->billing_limit,
						'trial_amount' 		=> $pmpro_level->trial_amount,
						'trial_limit' 		=> $pmpro_level->trial_limit,
						'startdate' 		=> $startdate,
						'enddate' 			=> $enddate);
					
					
				if (pmpro_changeMembershipLevel($custom_level, $user_id, 'changed'))
				{
					$order->status 							= "success";
					$order->subscription_transaction_id 	= "";
					$order->membership_id 					= $pmpro_level->id;
					$order->payment_transaction_id 			= $coinName." #".$payID;
					$order->saveOrder();
				}
			}

			return true;
		}
	}
}
  
   