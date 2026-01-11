<?php
/**
 * Plugin Name: DigitalDownloads4SnapForms - Instant downloads for SnapForms
 * Description: Enables instant file downloads upon form submission, using SnapForms.
 * Version:     1.0.0
 * Author:      Eduardo Esteves
 * Author URI: https://edluis97.github.io/ 
 */


if ( ! defined( 'ABSPATH' ) ) exit;

function getDigitalDownloads4SnapFormsConfigs() {
    require_once __DIR__.'/includes/Download.php';

    // Store config outside plugin: wp-content/snapforms/addons/digital-downloads/config.json
    $configsDir = trailingslashit(WP_CONTENT_DIR) . 'snapforms/addons/digital-downloads/config.json';
    try {
        $formsConfigs = \DigitalDownloads4SnapForms\Download::loadFormsConfig($configsDir);
    } catch (Exception $e) {
        // Fail gracefully if config is missing/invalid
        error_log('[DigitalDownloads4SnapForms] Config error: ' . $e->getMessage());
    }

    return $formsConfigs;
}

add_filter('query_vars', function ($vars) {
    $vars[] = 'id_form';
    $vars[] = 'form';
    $vars[] = 'id_submission';
    $vars[] = 'submission';
    $vars[] = 'token';
    return $vars;
});



add_action('snapforms.submission.new', function($args) {
    $formsConfigs = getDigitalDownloads4SnapFormsConfigs();

    $id_submission = $args['id_submission'];
    $id_form = $args['id_form'];

    if(empty($id_submission) || empty($id_form)) {
        return;
    }
    
    $formConfig = $formsConfigs[$id_form] ?? null;

    if (!$formConfig) {
        return; // No config for this form
    }

    $submission = apply_filters('snapforms_msgbus', "submission/".$id_form."/".$id_submission."/obtain")['data'] ?? null;
    if(empty($submission)) {
        print '<div class="notice notice-error">
            <p>Submission not found.</p>
        </div>';
        return;
    }

    $token = $submission['token'];

    $vrfy_link = home_url().'/forms/?sf_addon_action=sfdg_download_vrfy&form='.urlencode($submission['form_uuid']).'&submission='.urlencode($submission['uuid']).'&token='.urlencode($token);

    $replace = [
        '{recipient:name}' => $submission['recipient']['name'],
        '{vrfy_link}' => $vrfy_link,
    ];

    $msgBody = strtr($formConfig['emails']['verification']['body'] ?? '', $replace);
    
    apply_filters('snapforms_msgbus', "submission/".$id_form."/".$id_submission."/email/send", [
        "from_email" => $formConfig['emails']['verification']['email_address'] ?? '',
        'subject' => $formConfig['emails']['verification']['subject'] ?? 'Demo Site Request',
        'body' => $msgBody
    ]);
    
});



add_action('sfdg_download_vrfy', function() {    
    $formsConfigs = getDigitalDownloads4SnapFormsConfigs();

    $id_form = get_query_var('id_form');
    $form_uuid = get_query_var('form');
    $id_submission = get_query_var('id_submission');
    $submission_uuid = get_query_var('submission');
    $token = get_query_var('token');

    if((empty($id_form) && empty($form_uuid)) || (empty($id_submission) && empty($submission_uuid)) || empty($token)) {
        return;        
    }

    $search = [
        "form_uuid" => !empty($form_uuid) ? $form_uuid : '',
        "submission_uuid" => !empty($submission_uuid) ? $submission_uuid : '',
        "token" => $token,
        "external" => true,
    ];

    $form = apply_filters('snapforms_msgbus', "form/obtain", $search)['data'] ?? null;
    $submission = apply_filters('snapforms_msgbus', "submission/obtain", $search)['data'] ?? null;

    if(empty($form) ||empty($submission) || $submission['token'] != $token) {
        print '<div class="notice notice-error">
            <p>Invalid request parameters.</p>
        </div>';
        return;
    }

    $id_form = $submission['id_form'];
    $id_submission = $submission['id_submission'];

    $formConfig = $formsConfigs[$id_form] ?? null;
    if (!$formConfig) {
        return; // No config for this form
    }

    $title = $formConfig['title'] ?? $form['form'];
    print '<h2>'.$title.'</h2>';

    if($submission['status'] == '2') {//approved
        print '<div class="notice notice-error">
            <p>This download request has already been processed.</p>
        </div>';
        return;
    } elseif($submission['status'] != '1') {//not pending
        print '<div class="notice notice-error">
            <p>This download request is not available.</p>
        </div>';
        return;
    }

    $replace = [
        '{recipient:name}' => $submission['recipient']['name'],
    ];

    ?>
    <? if(!empty($formConfig['pages']['verification']['message'])): ?>
    <?= strtr($formConfig['pages']['verification']['message'], $replace) ?>
    <br>
    <? endif; ?>
    <form method="GET" onsubmit="snapformsFileDownload()" action="<?php echo home_url().'/forms/'; ?>" enctype="multipart/form-data">
        <input type="hidden" name="sf_addon_action" value="sfdg_download_confirm">
        <input type="hidden" name="form" value="<?php echo esc_attr($form_uuid); ?>">
        <input type="hidden" name="submission" value="<?php echo esc_attr($submission_uuid); ?>">
        <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

        <div class="text-end mt-3 p-3">
            <button type="submit" id="create-demo-btn" class="btn btn-primary"><?= $formConfig['pages']['verification']['button']['label'] ?? 'Download File' ?></button>
        </div>
    </form>

    <script>
        function snapformsFileDownload() {
            const btn = document.querySelector('#create-demo-btn');
            btn.disabled = true;
            btn.innerText = "<?= $formConfig['pages']['verification']['button']['post_click_label'] ?? 'Downloading File... Please wait.' ?>";
        }
    </script>
    <?php
});

add_action('sfdg_download_confirm', function() {  
    $formsConfigs = getDigitalDownloads4SnapFormsConfigs();

    $id_form = get_query_var('id_form');
    $form_uuid = get_query_var('form');
    $id_submission = get_query_var('id_submission');
    $submission_uuid = get_query_var('submission');
    $token = get_query_var('token');

    if((empty($id_form) && empty($form_uuid)) || (empty($id_submission) && empty($submission_uuid)) || empty($token)) {
        return;        
    }

    $search = [
        "form_uuid" => !empty($form_uuid) ? $form_uuid : '',
        "submission_uuid" => !empty($submission_uuid) ? $submission_uuid : '',
        "token" => $token,
        "external" => true,
    ];

    $form = apply_filters('snapforms_msgbus', "form/obtain", $search)['data'] ?? null;
    $submission = apply_filters('snapforms_msgbus', "submission/obtain", $search)['data'] ?? null;

    if(empty($form) ||empty($submission) || $submission['token'] != $token) {
        print '<div class="notice notice-error">
            <p>Invalid request parameters.</p>
        </div>';
        return;
    }

    print '<h2>'.$title.'</h2>';

    if($submission['status'] == '2') {//approved
        print '<div class="notice notice-error">
            <p>This download request has already been processed.</p>
        </div>';
        return;
    } elseif($submission['status'] != '1') {//not pending
        print '<div class="notice notice-error">
            <p>This download request is not available.</p>
        </div>';
        return;
    }

    $id_form = $submission['id_form'];
    $id_submission = $submission['id_submission'];

    apply_filters('snapforms_msgbus', "submission/".$id_form."/".$id_submission."/approve");    

    $formConfig = $formsConfigs[$id_form] ?? null;
    if (!$formConfig) {
        return; // No config for this form
    }

    $replace = [
        '{recipient:name}' => $submission['recipient']['name'],
    ];

    if(!empty($formConfig['pages']['success']['message'])) {
        print strtr($formConfig['pages']['success']['message'], $replace);
    }

    // Auto-trigger the download while staying on the success page
    $serve_args = [
        'sf_addon_action' => 'sfdg_download_serve',
        'form' => $form_uuid,
        'submission' => $submission_uuid,
        'token' => $token,
    ];
    $serve_url = add_query_arg($serve_args, home_url().'/forms/');

    echo '<iframe src="'.esc_url($serve_url).'" style="display:none;" title="download"></iframe>';
});

// Dedicated endpoint to serve the file so redirects don't interrupt download
add_action('sfdg_download_serve', function() {
    $formsConfigs = getDigitalDownloads4SnapFormsConfigs();

    $id_form = get_query_var('id_form');
    $form_uuid = get_query_var('form');
    $id_submission = get_query_var('id_submission');
    $submission_uuid = get_query_var('submission');
    $token = get_query_var('token');

    if((empty($id_form) && empty($form_uuid)) || (empty($id_submission) && empty($submission_uuid)) || empty($token)) {
        status_header(400);
        exit;
    }

    $search = [
        "form_uuid" => !empty($form_uuid) ? $form_uuid : '',
        "submission_uuid" => !empty($submission_uuid) ? $submission_uuid : '',
        "token" => $token,
        "external" => true,
    ];

    $form = apply_filters('snapforms_msgbus', "form/obtain", $search)['data'] ?? null;
    $submission = apply_filters('snapforms_msgbus', "submission/obtain", $search)['data'] ?? null;

    if(empty($form) || empty($submission) || $submission['token'] != $token) {
        status_header(403);
        exit;
    }

    // Only allow approved submissions to download
    if($submission['status'] != '2') {
        status_header(409);
        exit;
    }

    $id_form = $submission['id_form'];
    $formConfig = $formsConfigs[$id_form] ?? null;
    if (!$formConfig) {
        status_header(404);
        exit;
    }

    $download = new \DigitalDownloads4SnapForms\Download($formConfig);
    $download->serveFile();
    exit;
});