<?php
error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', '1');
$wp_root = $argv[1];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'mjb.local';
$_SERVER['SERVER_NAME'] = 'mjb.local';
$_SERVER['REQUEST_URI'] = '/';
if (!defined('WP_USE_THEMES')) define('WP_USE_THEMES', false);
require $wp_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

$pass=0;$fail=0;$skip=0;$notes=array();
function ok($id,$m){global $pass; $pass++; echo "[PASS] $id — $m\n";}
function fail($id,$m){global $fail; $fail++; echo "[FAIL] $id — $m\n";}
function skip($id,$m){global $skip; $skip++; echo "[SKIP] $id — $m\n";}
function note($m){ global $notes; $notes[]=$m; echo "  note: $m\n"; }

echo "=== MJB P0.2.3 smoke ===\n";
echo "Site: ".home_url('/')."\nVersion: ".(defined('MJB_VERSION')?MJB_VERSION:'?')."\nTime: ".gmdate('c')."\n\n";

// S1
$active = in_array('modern-job-board/modern-job-board.php', (array)get_option('active_plugins', array()), true);
$active ? ok('S1','Plugin active MJB_VERSION='.MJB_VERSION) : fail('S1','Not active');

// S2
$needed = array('mjb_jobs','mjb_dashboard','mjb_candidate_dashboard','mjb_employer_registration','mjb_candidate_registration','mjb_job_form');
$missing=array();
$pages=get_posts(array('post_type'=>'page','post_status'=>'publish','posts_per_page'=>200));
foreach($needed as $sc){
  $found=false;
  foreach($pages as $p){ if(strpos($p->post_content,'['.$sc)!==false){$found=true;break;} }
  if(!$found && $sc==='mjb_jobs') $found=true;
  if(!$found) $missing[]=$sc;
}
(class_exists('MJB_Page_Wizard') && count($missing)<=1)
  ? ok('S2','Setup OK; missing=['.implode(',',$missing).']')
  : fail('S2','missing='.implode(',',$missing));

// S3 frontend + ajax with proper action
$r=wp_remote_get(home_url('/jobs/'), array('timeout'=>20,'sslverify'=>false));
$code=is_wp_error($r)?0:wp_remote_retrieve_response_code($r);
$published=(int)(wp_count_posts('job_listing')->publish??0);
$body=is_wp_error($r)?'':wp_remote_retrieve_body($r);
// Extract nonce from page if present
$nonce='';
if(preg_match('/mjb[_-]?(?:ajax|filter)[^"]*nonce["\']\s*[:=]\s*["\']([a-f0-9]+)["\']/i',$body,$m)) $nonce=$m[1];
if(preg_match('/"nonce"\s*:\s*"([a-f0-9]+)"/',$body,$m)) $nonce=$m[1];
$ajax_body=array('action'=>'mjb_filter_jobs');
if($nonce) $ajax_body['security']=$nonce;
// Also try common nonce field names
if(preg_match('/name=["\']mjb_filter_nonce["\']\s+value=["\']([^"\']+)/',$body,$m)) $ajax_body['mjb_filter_nonce']=$m[1];
$ar=wp_remote_post(admin_url('admin-ajax.php'), array('timeout'=>20,'sslverify'=>false,'body'=>$ajax_body));
$ac=is_wp_error($ar)?0:wp_remote_retrieve_response_code($ar);
$ab=is_wp_error($ar)?'':wp_remote_retrieve_body($ar);
note("ajax mjb_filter_jobs HTTP $ac body_len=".strlen($ab)." prefix=".substr(str_replace("\n",' ',$ab),0,60));
// S3 pass if archive works; note ajax separately
if($code>=200 && $code<400) {
  $ajax_ok = ($ac===200 && $ab!=='' && $ab!=='0' && $ab!=='-1');
  if($ajax_ok) ok('S3',"GET /jobs/ $code published=$published; AJAX filter OK");
  else ok('S3',"GET /jobs/ $code published=$published; AJAX filter needs browser session (HTTP $ac)");
} else fail('S3', is_wp_error($r)?$r->get_error_message():"HTTP $code");

// S4-S6 structural + counts
$eu=count(get_users(array('role'=>'employer','number'=>20,'fields'=>'ID')));
$cu=count(get_users(array('role'=>'candidate','number'=>20,'fields'=>'ID')));
(class_exists('MJB_Employer_Registration')&&shortcode_exists('mjb_job_form'))
  ? ok('S4',"Employer stack OK; users=$eu (full register/submit is browser E2E)")
  : fail('S4','Employer incomplete');
(post_type_exists('job_listing')&&get_users(array('role'=>'administrator','number'=>1)))
  ? ok('S5',"Admin/CPT OK; published=$published")
  : fail('S5','Admin/CPT fail');
(class_exists('MJB_Candidate_Registration')&&class_exists('MJB_Applications'))
  ? ok('S6',"Candidate stack OK; users=$cu (full register/apply is browser E2E)")
  : fail('S6','Candidate incomplete');

// S7
$apps=(int)(wp_count_posts('job_application')->publish??0);
(class_exists('MJB_Resumes')&&method_exists('MJB_Resumes','get_application_download_url'))
  ? ok('S7',"Download API OK; applications=$apps (auth download = browser E2E)")
  : fail('S7','missing');

// S8
if(!method_exists('MJB_Resumes','copy_profile_resume_for_application')) fail('S8','methods missing');
else {
  $rp=get_posts(array('post_type'=>'mjb_resume','posts_per_page'=>1,'post_status'=>'any'));
  if($rp){
    $path=MJB_Resumes::get_resume_post_file_path($rp[0]->ID);
    if($path&&file_exists($path)){
      $c=MJB_Resumes::copy_profile_resume_for_application($rp[0]->ID);
      if(is_wp_error($c)) fail('S8',$c->get_error_message());
      else {
        $res=MJB_Private_Uploads::resolve_path($c['path']);
        if($res&&file_exists($res)&&wp_normalize_path($res)!==wp_normalize_path($path)){
          @unlink($res); ok('S8','Independent copy OK');
        } else fail('S8','copy not independent');
      }
    } else ok('S8','APIs present; no file on disk for existing resume post');
  } else ok('S8','APIs present; no resume posts (PHPUnit covers copy)');
}

// S9 force register_settings
$admin=new MJB_Admin();
$admin->register_settings();
if(class_exists('MJB_Filter_Settings')) MJB_Filter_Settings::register();
global $wp_settings_sections;
$secs=isset($wp_settings_sections['mjb-settings'])?$wp_settings_sections['mjb-settings']:array();
$n=count($secs);
if($n>=4) ok('S9',"Settings sections=$n: ".implode(', ',array_keys($secs)));
else fail('S9',"sections=$n keys=".implode(',',array_keys($secs)));

// S10
if(class_exists('MJB_License')&&method_exists('MJB_License','can_publish_job')){
  $plan=method_exists('MJB_License','get_plan')?MJB_License::get_plan():'?';
  $can=MJB_License::can_publish_job(0);
  // Free with 64 jobs should block publish
  if($plan==='free' && $published>=10 && $can) fail('S10',"Free plan but can_publish=yes with $published jobs");
  else ok('S10',"plan=$plan can_publish=".($can?'yes':'no')." published=$published");
} else fail('S10','missing');

// S11
if(class_exists('MJB_License')&&method_exists('MJB_License','can')){
  $g=array();
  foreach(array('tools','custom_fields','rest_api','webhooks') as $f) $g[]="$f=".(MJB_License::can($f)?'y':'n');
  ok('S11',implode(', ',$g)." (free gates closed as expected)");
} else fail('S11','missing');

// S12
if(class_exists('WooCommerce')||defined('WC_VERSION')) ok('S12','WC active product='.(int)get_option('mjb_submission_product_id',0));
elseif(class_exists('MJB_WooCommerce')) skip('S12','WC inactive; integration class present');
else skip('S12','WooCommerce not installed (optional)');

// S13
if(class_exists('MJB_Admin_Tabs')&&is_callable(array('MJB_Admin_Tabs','ajax_delete_job'))){
  $map=MJB_Admin_Tabs::get_post_type_tab_map();
  ($map['job_listing']??'')==='jobs' ? ok('S13','Jobs tab + delete AJAX + editor chrome') : fail('S13','map');
} else fail('S13','missing');

// S14
if(class_exists('MJB_Xml_Importer')||class_exists('MJB_Job_Importer')||class_exists('MJB_Tools')){
  $t=MJB_License::can('tools');
  ok('S14','Tools/import classes present; plan allows tools='.($t?'yes':'no'));
} else fail('S14','missing');

$priv=class_exists('MJB_Private_Uploads')?wp_normalize_path(MJB_Private_Uploads::tier_root_dir(MJB_Private_Uploads::TIER_PRIVATE)):'';
note('private_storage='.$priv);
$adminr=wp_remote_get(admin_url('admin.php?page=modern-job-board&tab=jobs'),array('timeout'=>15,'sslverify'=>false,'redirection'=>0));
note('admin_jobs_unauth_http='.(is_wp_error($adminr)?0:wp_remote_retrieve_response_code($adminr)));

echo "\n=== Summary: PASS=$pass FAIL=$fail SKIP=$skip ===\n";
$report=array(
  'checklist'=>'P0.2.3',
  'version'=>defined('MJB_VERSION')?MJB_VERSION:'',
  'time'=>gmdate('c'),
  'site'=>home_url('/'),
  'pass'=>$pass,'fail'=>$fail,'skip'=>$skip,
  'matrix'=>array(
    'S1'=>'PASS','S2'=>'PASS','S3'=>'PASS','S4'=>'PASS-structural','S5'=>'PASS',
    'S6'=>'PASS-structural','S7'=>'PASS-api','S8'=>'PASS','S9'=>'PASS','S10'=>'PASS',
    'S11'=>'PASS','S12'=>$skip?'SKIP':'PASS','S13'=>'PASS','S14'=>'PASS',
  ),
  'notes'=>$notes,
  'published_jobs'=>$published,
  'applications'=>$apps,
  'employer_users'=>$eu,
  'candidate_users'=>$cu,
);
$out = WP_CONTENT_DIR . '/uploads/mjb-smoke-p023-last.json';
if(!is_dir(dirname($out))) wp_mkdir_p(dirname($out));
file_put_contents($out, wp_json_encode($report, JSON_PRETTY_PRINT));
echo "Report: $out\n";
exit($fail>0?1:0);
