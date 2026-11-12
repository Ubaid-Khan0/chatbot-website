<?php
declare(strict_types=1);
use MongoDB\BSON\UTCDateTime;
use Nexum\Database;
require dirname(__DIR__) . '/src/bootstrap.php';
$db = Database::connection();
$db->users->createIndex(['email'=>1], ['unique'=>true,'name'=>'users_email_unique']);
$db->users->createIndex(['role'=>1,'active'=>1], ['name'=>'users_role_active']);
$db->leads->createIndex(['created_at'=>-1], ['name'=>'leads_created_desc']);
$db->leads->createIndex(['status'=>1,'created_at'=>-1], ['name'=>'leads_status_created']);
$db->leads->createIndex(['source_ip'=>1,'created_at'=>-1], ['name'=>'leads_rate_limit']);
$db->plans->createIndex(['slug'=>1], ['unique'=>true,'name'=>'plans_slug_unique']);
$db->site_content->createIndex(['key'=>1], ['unique'=>true,'name'=>'content_key_unique']);
$db->audit_logs->createIndex(['created_at'=>-1], ['name'=>'audit_created_desc']);
$now = new UTCDateTime();
$plans = [
 ['slug'=>'starter','name'=>'Starter','starting_price_pkr'=>150000,'price_suffix'=>'','description'=>'Focused chatbot for essential questions and a clear customer journey.','features'=>['Focused conversation flow','Core knowledge setup','Lead capture','Launch support'],'active'=>true,'sort_order'=>1],
 ['slug'=>'growth','name'=>'Growth','starting_price_pkr'=>300000,'price_suffix'=>'','description'=>'Multiple flows, lead qualification, and selected workflow connections.','features'=>['Multiple conversation flows','Lead qualification','Selected integrations','Analytics handoff'],'active'=>true,'sort_order'=>2],
 ['slug'=>'custom','name'=>'Custom','starting_price_pkr'=>600000,'price_suffix'=>'+','description'=>'Complex journeys, larger knowledge sets, and advanced integrations.','features'=>['Advanced integrations','Larger knowledge sets','Custom workflows','Priority delivery plan'],'active'=>true,'sort_order'=>3],
];
foreach ($plans as $plan) $db->plans->updateOne(['slug'=>$plan['slug']], ['$setOnInsert'=>$plan + ['created_at'=>$now,'updated_at'=>$now]], ['upsert'=>true]);
$defaults = ['heroTitle'=>'Every conversation. Connected.','heroLead'=>'An AI chatbot built around your business—ready to answer, guide, qualify, and support your customers around the clock.','ctaTitle'=>'Find the right chatbot package.','contactEmail'=>'info@nexumglobal.solutions'];
foreach ($defaults as $key=>$value) $db->site_content->updateOne(['key'=>$key], ['$setOnInsert'=>['key'=>$key,'value'=>$value,'created_at'=>$now,'updated_at'=>$now]], ['upsert'=>true]);
echo "MongoDB indexes and default content are ready.\n";
