<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json');
require_once 'db.php';
require_once 'paypal-config.php';

if (empty($_SESSION['customer_id'])) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$request_id = (int)($in['request_id'] ?? 0);
$payment_id = (int)($in['payment_id'] ?? 0);
$order_id   = trim($in['order_id'] ?? '');
$amount_php = (float)($in['amount'] ?? 0);
$order_json = $in['payment_details'] ?? null;

if (!$request_id || !$payment_id || !$order_id || !$amount_php) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Missing fields']); exit; }

// confirm ownership
$sql="SELECT p.payment_id,sr.customer_id FROM payment p JOIN service_request sr ON sr.request_id=p.request_id WHERE p.payment_id=? AND p.request_id=? LIMIT 1";
$st=mysqli_prepare($conn,$sql); mysqli_stmt_bind_param($st,'ii',$payment_id,$request_id); mysqli_stmt_execute($st);
$res=mysqli_stmt_get_result($st); $row=mysqli_fetch_assoc($res); mysqli_stmt_close($st);
if(!$row || (int)$row['customer_id']!==(int)$_SESSION['customer_id']){ http_response_code(403); echo json_encode(['success'=>false,'message'=>'Payment not found or not yours']); exit; }

// PayPal token + order GET
function pp_token(){
  $ch=curl_init(PAYPAL_API_URL."/v1/oauth2/token");
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_USERPWD=>PAYPAL_CLIENT_ID.":".PAYPAL_CLIENT_SECRET,CURLOPT_POSTFIELDS=>"grant_type=client_credentials"]);
  $r=curl_exec($ch); if($r===false) throw new Exception('PayPal token error: '.curl_error($ch));
  $d=json_decode($r,true); if(empty($d['access_token'])) throw new Exception('PayPal token missing'); return $d['access_token'];
}
function pp_order($tok,$id){
  $ch=curl_init(PAYPAL_API_URL."/v2/checkout/orders/".urlencode($id));
  curl_setopt_array($ch,[CURLOPT_HTTPGET=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["Authorization: Bearer $tok","Content-Type: application/json"]]);
  $r=curl_exec($ch); if($r===false) throw new Exception('PayPal order error: '.curl_error($ch));
  return json_decode($r,true);
}

try{
  $tok=pp_token();
  $order=pp_order($tok,$order_id); // Show order details API. :contentReference[oaicite:7]{index=7}
  if(empty($order['status']) || $order['status']!=='COMPLETED'){ throw new Exception('Order not completed'); }

  // Optional amount sanity check (USD capture; keep rate in paypal-config.php)
  if (defined('PHP_TO_USD_RATE') && PHP_TO_USD_RATE>0){
    $pu=$order['purchase_units'][0]??[]; $usd=(float)($pu['amount']['value']??0); $cc=($pu['amount']['currency_code']??'USD');
    $expected=round($amount_php/(float)PHP_TO_USD_RATE,2);
    if($cc!=='USD' || abs($usd-$expected)>0.05) throw new Exception('Amount mismatch');
  }

  $details = json_encode($order_json ?: $order, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

  // write to DB (uses your existing columns) :contentReference[oaicite:8]{index=8}
  $upd="UPDATE payment SET amount=?, payment_status='paid', payment_date=NOW(), paypal_order_id=?, paypal_details=?, receiver_name=COALESCE(receiver_name,'LANCE COOLING SERVICES') WHERE payment_id=? AND request_id=? LIMIT 1";
  $st=mysqli_prepare($conn,$upd); mysqli_stmt_bind_param($st,'dssii',$amount_php,$order_id,$details,$payment_id,$request_id);
  if(!mysqli_stmt_execute($st)){ throw new Exception('DB update failed: '.mysqli_error($conn)); }
  mysqli_stmt_close($st);

  // notifications (admin + customer)
  $admin_id = 1; $cust_id=(int)$_SESSION['customer_id'];
  $ins="INSERT INTO notifications (user_type,user_id,title,message,request_id) VALUES
        ('admin',?,'Payment received',CONCAT('Payment for Request #',?, ' via PayPal. Amount ₱',FORMAT(?,2)),?),
        ('customer',?,'Payment successful',CONCAT('Thanks! Your payment for Request #',?,' is completed.'),?)";
  $st=mysqli_prepare($conn,$ins); mysqli_stmt_bind_param($st,'iidiiii',$admin_id,$request_id,$amount_php,$request_id,$cust_id,$request_id,$request_id);
  mysqli_stmt_execute($st); mysqli_stmt_close($st);

  echo json_encode(['success'=>true]);
}catch(Exception $e){
  http_response_code(400); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
