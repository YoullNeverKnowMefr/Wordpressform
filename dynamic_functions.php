<?php

function wtw_forms_extentions()
{
    $extentions = [];
    foreach (get_field('wtw_forms_extentions', 'option') as $extention) {
        if ($extention['enabled']) {
            $extentions[$extention['acf_fc_layout']][] = $extention;
        }
    }
    return $extentions;
}

function wtw_log($message)
{
    if (!WP_DEBUG) return;

    $primary = get_stylesheet_directory() . '/bitrix.log';
    $fallback = WP_CONTENT_DIR . '/bitrix.log';
    
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    
    $line = date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL;
    $ok = false;
    
    if (is_writable(dirname($primary))) {
        $ok = @file_put_contents($primary, $line, FILE_APPEND | LOCK_EX) !== false;
    }
    if (!$ok) {
        $ok = @file_put_contents($fallback, $line, FILE_APPEND | LOCK_EX) !== false;
    }
    if (!$ok) {
        error_log($line);
    }
}

add_action('init', function(){
    // wtw_log('Bitrix logger initialized');
});

function ajaxs_wtw_mail_sent($jx)
{
    // if (!wp_verify_nonce($jx->ajaxs_nonce, 'ajaxs_action'))
    //     $jx->error('Ошибка. Неправильный код проверки!');

    $form_data = $jx->data;

    $forms = get_field('wtw_forms', 'option');

    if (!isset($form_data['__forms'])) {
        $jx->error('Ошибка: Формы не настроены!');
    }

    do_action('mailer_before_parse_fields', $form_data);
    $form_data = apply_filters('mailer_before_parse_fields_filter', $form_data);

    $form_id = $form_data['__forms'];

    $form_setup = $forms[$form_id];

    $extentions = wtw_forms_extentions();

    $result = [];

    $result_fields = [
        'hide',
        'delay',
        'lbox_hide',
        'hide_duration',
        'success_message',
        'error_message',
        'redirect',
        'redirect_url',
        'redirect_new_tab'
    ];

    foreach ($result_fields as $field) {
        $result[$field] = $form_setup[$field];
    }

    $form_data['__fields'] = "";
    foreach ($form_data as $key => $value) {

        if (in_array($key, [
            'ajaxs_nonce',
            'email_confirm',
            'form_time',
            'sfa_captcha_answer',
            'sfa_captcha_keys',
            'cf-turnstile-response',
            ]) || strpos($key, '__') === 0) continue;

        if ($value === 'on') {
            $value = '✔';
        }

        if (is_array($value)) {
            $form_data['__fields'] .= str_replace('_', ' ', $key) . ': <b>' . implode(', ', $value) . '</b> <br />';
        } else {
            if (!empty($value)) {
                $form_data['__fields'] .= str_replace('_', ' ', $key) . ': <b>' . $value . '</b> <br />';
            }
        }
    }

    $subject = wtw_proccesFieldTemplate($form_setup['subject'], $form_data);
    $message = wtw_proccesFieldTemplate($form_setup['message'], $form_data, !$form_setup['show_empty_fields']);

    $headers = [];
    $headers[] = 'Content-Type: text/html; charset=' . get_bloginfo('charset');

    if (!empty($form_setup['addreply'])) {
        if (!empty($form_setup['from'])) {
            $headers[] = 'Reply-To: ' . $form_setup['from'] . ' <' . $form_setup['addreply'] . '>';
        } else {
            $headers[] = 'Reply-To: ' . $form_setup['addreply'];
        }
    }

    if (!empty($form_setup['cc'])) {
        $headers[] = 'Cc: ' . $form_setup['cc'];
    }

    if (!empty($form_setup['bcc'])) {
        $headers[] = 'Bcc: ' . $form_setup['bcc'];
    }

    $email = $form_setup['email'];

    $attachments = wtw_getLoadedFilesList($jx->files);

    wtw_handle_extentions($form_data, $attachments);

    if (!empty($email) && $form_setup['enabled']) {
        $mail_sended = wp_mail($email, $subject, $message, $headers, $attachments);
    } else {
        $mail_sended = true;
    }

    if ($mail_sended) {
        $jx->success($result);
    } else {
        $jx->error($result);
    }
}

function wtw_handle_extentions($form_data, $attachments)
{
    $extentions = get_field('wtw_forms_extentions', 'option');

    $wtw_forms_extentions = get_field('wtw_forms_extentions', 'option');

    $form_setup = [];
    $extentions_data = [];

    foreach ($wtw_forms_extentions as $extention) {
        if (!empty($extention['selector']) && $extention['enabled']) {
            $extentions_data[$extention['acf_fc_layout']][] = $extention;
        }
    }

    $utm = [];
    $args = ltrim($form_data['__query'], '?');
    parse_str($args, $utm);

    foreach ($utm as $key => $value) {
        $form_data[$key] = $value;
    }

    if (is_array($attachments) && !empty($attachments)) {
        $form_data['__attachments'] = $attachments;
    }

    $extention_field = '__google_recaptcha';
    if (isset($form_data[$extention_field])) {

        $setup_id = $form_data[$extention_field];
        $form_setup = $extentions_data[$extention_field][$setup_id];

        $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
        $recaptcha_secret = $form_setup['recaptcha_secret_key'];
        $recaptcha_response = $form_data['__recaptcha_response'];

        if (!empty($recaptcha_response)) {
            $response = file_get_contents($recaptcha_url . '?secret=' . $recaptcha_secret . '&response=' . $recaptcha_response);
            $responseKeys = json_decode($response, true);

            if (!$responseKeys["success"] || $responseKeys["score"] < 0.5) {
                jx()->error('Ошибка: Проверка капчи не прошла!');
            }
        }
    }

    $extention_field = '__sender_reply';
    if (isset($form_data[$extention_field])) {

        $reply_attachments = [];

        $setup_id = $form_data[$extention_field];
        $form_setup = $extentions_data[$extention_field][$setup_id];

        if (!empty($form_setup['reply_file'])) {
            $upload_dir = wp_upload_dir();
            $reply_file = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $form_setup['reply_file']);
            $reply_attachments[] = $reply_file;
        }

        $headers = [
            'content-type: text/html',
        ];

        $email = $form_data[$form_setup['reply_email']];

        $subject = wtw_proccesFieldTemplate($form_setup['reply_subject'], $form_data);
        $message = wtw_proccesFieldTemplate($form_setup['reply_message'], $form_data, !$form_setup['show_empty_fields']);

        if (!empty($email)) {
            $mail_sended = wp_mail($email, $subject, $message, $headers, $reply_attachments);

            if (!$mail_sended) {
                jx()->error($result);
            }
        }
    }

    $extention_field = '__telegram';
    if (isset($form_data[$extention_field])) {

        $setup_id = $form_data[$extention_field];
        $form_setup = $extentions_data[$extention_field][$setup_id];

        $message = wtw_proccesFieldTemplate($form_setup['template'], $form_data, !$form_setup['show_empty_fields']);

        $message = str_replace(['<b>', '</b>'], '*', $message);
        $message = str_replace('<br />', "\n", $message);

        $botToken = $form_setup['token'];
        $botURL = "https://api.telegram.org/bot" . $botToken;
        $chatId = $form_setup['chat_id'];

        $params = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ];

        $response = wp_remote_post($botURL . '/sendMessage', [
            'body' => $params,
            'sslverify' => false,
        ]);

        if ($form_setup['send_files']) {
            if (is_array($attachments) && count($attachments) > 0) {
                foreach ($attachments as $path) {
                    $params = [
                        'chat_id' => $chatId,
                        'document' => curl_file_create($path, '', basename($path)),
                    ];
                    $ch = curl_init($botURL . '/sendDocument');
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $result = curl_exec($ch);
                    curl_close($ch);
                }
            } else {
                foreach ($_FILES as $fieldName => $filePost) {
                    $files_array = reArrayFiles($filePost);
                    if ($files_array !== false) {
                        foreach ($files_array as $file) {
                            if ($file['error'] === UPLOAD_ERR_OK) {
                                $params = [
                                    'chat_id' => $chatId,
                                    'document' => curl_filereate($file['tmp_name'], '', $file['name']),
                                ];
                                $ch = curl_init($botURL . '/sendDocument');
                                curl_setopt($ch, CURLOPT_HEADER, false);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                $result = curl_exec($ch);
                                curl_close($ch);
                            }
                        }
                    }
                }
            }
        }

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $jx->error($error_message);
        }
    }

    $extention_field = '__bitrix';
    if (isset($form_data[$extention_field])) {

        if (!function_exists('wtw_createBitrixDeal')) {
            jx()->alert('Расширение BITRIX не активировано! Обратитесь к разработчику.');
        } else {

            wtw_log('Bitrix: extension triggered');

            $lead_id = null;
            $deal_id = null;

            $setup_id = $form_data[$extention_field];
            $form_setup = $extentions_data[$extention_field][$setup_id];

            $api_url = $form_setup['bitrix_api_url'];

            $entities = [];
            foreach ($form_setup['entities'] as $entity) {
                $entities[$entity['acf_fc_layout']] = $entity;
                unset($entities[$entity['acf_fc_layout']]['acf_fc_layout']);
            }

            wtw_log('Bitrix: entities prepared: ' . implode(', ', array_keys($entities)));

            $entity = $entities['contact'];
            if (isset($entity)) {
                wtw_log('Bitrix: create contact');
                $contact_id = wtw_createBitrixContact($api_url, $form_data, $entity);
                wtw_log('Bitrix: contact id=' . ($contact_id ?: '')); 
            }

            $entity = $entities['lead'];
            if (isset($entity)) {
                if (isset($contact_id) && !empty($contact_id)) {
                    $entity['CONTACT_ID'] = $contact_id;
                }
                wtw_log('Bitrix: create lead');
                $lead_id = wtw_createBitrixLead($api_url, $form_data, $entity);
                wtw_log('Bitrix: lead id=' . ($lead_id ?: ''));
            }

            $entity = $entities['deal'];
            if (isset($entity)) {
                if (isset($contact_id) && !empty($contact_id)) {
                    $entity['CONTACT_ID'] = $contact_id;
                }
                wtw_log('Bitrix: create deal');
                $deal_id = wtw_createBitrixDeal($api_url, $form_data, $entity);
                wtw_log('Bitrix: deal id=' . ($deal_id ?: ''));
            }

            $entity = $entities['comment'];
            if (isset($entity)) {
                if (isset($deal_id) && !empty($deal_id)) {
                    $entity['ENTITY_TYPE'] = 'deal';
                    $entity['ENTITY_ID'] = $deal_id;
                    wtw_log('Bitrix: add comment to deal');
                    $comment_id = wtw_createBitrixComment($api_url, $form_data, $entity);
                } else if (isset($lead_id) && !empty($lead_id)) {
                    $entity['ENTITY_TYPE'] = 'lead';
                    $entity['ENTITY_ID'] = $lead_id;
                    wtw_log('Bitrix: add comment to lead');
                    $comment_id = wtw_createBitrixComment($api_url, $form_data, $entity);
                }
            }
        }
    }

    $extention_field = '__amo';
    if (isset($form_data[$extention_field])) {

        if (!function_exists('wtw_createAMOLead')) {
            jx()->alert('Расширение АМО не активировано! Обратитесь к разработчику.');
        } else {

            $setup_id = $form_data[$extention_field];
            $form_setup = $extentions_data[$extention_field][$setup_id];

            $entities = [];
            foreach ($form_setup['entities'] as $entity) {
                $entities[$entity['acf_fc_layout']] = $entity;
                unset($entities[$entity['acf_fc_layout']]['acf_fc_layout']);
            }

            $lead_id = null;

            $form_setup['entities'] = $entities;

            if (isset($entities['lead'])) {
                $lead_id = wtw_createAMOLead($form_setup, $form_data);
            }

            if (isset($entities['note']) && $lead_id !== null) {
                $form_setup['entities']['note']['lead_id'] = $lead_id;
                wtw_createAMONote($form_setup, $form_data);
            }
        }
    }
}

function wtw_proccesFieldTemplate($template, $form_data, $remove_empty_fields = false)
{
    $form_data['__ip'] = $_SERVER['REMOTE_ADDR'];
    $form_data['__site'] = $_SERVER['HTTP_HOST'];
    $form_data['__browser'] = $_SERVER['HTTP_USER_AGENT'];

    foreach ($form_data as $key => $value) {
        if (is_string($value)) {
            $template = str_replace("{{ $key }}", $value, $template);
        }
    }

    $template = str_replace("{{ now }}", date('d.m.Y H:i:s'), $template);

    if ($remove_empty_fields) {
        $template = preg_replace('/^.*\{\{[^\}]*\}\}.*$\n?/m', '', $template);
    } else {
        $template = preg_replace('/\{\{[^\}]*\}\}/', '', $template);
    }

    return $template;
}

function wtw_getLoadedFilesList($files)
{
    $uploaded_files_paths = [];

    $fileFields = array_keys($files);

    foreach ($fileFields as $fieldName) {

        if (!is_array($files[$fieldName]['compact'])) continue;

        foreach ($files[$fieldName]['compact'] as $file) {

            $movefile = wp_handle_upload($file, ['test_form' => false]);

            if ($movefile && !isset($movefile['error'])) {
                $uploaded_files_paths[] = $movefile['file'];
            }
        }
    }
    return $uploaded_files_paths;
}

add_action('wp_mail_failed', 'wtw_log_mailer_errors', 10, 1);
function wtw_log_mailer_errors($wp_error)
{
    error_log($wp_error->get_error_message());
}

add_action('phpmailer_init', 'wtw_smtp_phpmailer_init', 999);
function wtw_smtp_phpmailer_init($phpmailer)
{
    $extention_name = '__smtp';
    $extentions = wtw_forms_extentions();

    if (!isset($extentions[$extention_name])) {
        return;
    }

    $setup = $extentions[$extention_name][0];
    $sender_email = isset($setup['sender_email']) && !empty($setup['sender_email']) ? $setup['sender_email'] : $setup['smtp_user'];
    $sender_name = isset($setup['sender_name']) && !empty($setup['sender_name']) ? $setup['sender_name'] : null;

    $phpmailer->IsSMTP();
    $phpmailer->SMTPAuth   = $setup['smtp_auth'];
    $phpmailer->Host       = $setup['smtp_server'];
    $phpmailer->Port       = $setup['smtp_port'];
    $phpmailer->CharSet    = $setup['smtp_encoding'];
    $phpmailer->SMTPSecure = $setup['smtp_secure'];
    $phpmailer->Username   = $setup['smtp_user'];
    $phpmailer->Password   = $setup['smtp_password'];
    $phpmailer->From       = $sender_email;
    if ($sender_name !== null) $phpmailer->FromName = $sender_name;
    $phpmailer->isHTML(true);
}

function get_wtw_smtp_settings($name){
    $extention_name = '__smtp';
    $extentions = wtw_forms_extentions();

    if (!isset($extentions[$extention_name])) {
        return null;
    }

    $setup = $extentions[$extention_name][0];

    if (!isset($setup[$name])) {
        return null;
    }

    return $setup[$name];
}

add_filter('wp_mail_from', function($email) {
    return get_wtw_smtp_settings('sender_email') ? get_wtw_smtp_settings('sender_email') : get_wtw_smtp_settings('smtp_user');
});

add_filter('wp_mail_from_name', function($name) {
    return get_wtw_smtp_settings('sender_name') ? get_wtw_smtp_settings('sender_name') : $name;
});

add_action('wp_footer', 'wtw_google_recaptcha_load');
function wtw_google_recaptcha_load()
{
    $extention_name = '__google_recaptcha';
    $extentions = wtw_forms_extentions();

    if (!isset($extentions[$extention_name])) {
        return;
    }

    $setup = $extentions[$extention_name][0];
?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo $setup['recaptcha_site_key'] ?>"></script>
    <script>
        const RECAPTCHA_SITE_KEY = `<?php echo $setup['recaptcha_site_key'] ?>`;
    </script>
<?php
}

add_action('wp_footer', 'wtw_validate_data_load');
function wtw_validate_data_load()
{
    $extention_name = '__validate';
    $extentions = wtw_forms_extentions();

    if (!isset($extentions[$extention_name])) {
        return;
    }

    $setup = $extentions[$extention_name][0];
?>
    <script>
        const VALIDATE_DATA = <?php echo json_encode($setup['validate_fields']) ?>;
    </script>
<?php
}

function wtw_forms_data_load()
{
    wp_enqueue_script('forms', get_stylesheet_directory_uri() . '/js/forms.js', ['justvalidate'], null, true);

    $wtw_forms = get_field('wtw_forms', 'option');
    $wtw_forms_extentions = get_field('wtw_forms_extentions', 'option');
    $forms_data = ['__forms' => []];

    foreach ($wtw_forms as $form) {
        $forms_data['__forms'][] = $form['selector'];
    }

    if (!empty($wtw_forms_extentions) && is_array($wtw_forms_extentions)) {
        foreach ($wtw_forms_extentions as $extention) {
            if (!empty($extention['selector']) && $extention['enabled']) {
                $forms_data[$extention['acf_fc_layout']][] = $extention['selector'];
            }
        }
    }

    wp_localize_script('forms', 'wtw_forms', $forms_data);
}

add_action('wp_enqueue_scripts', 'wtw_forms_data_load');

add_action('init', function () {
    if (function_exists('acf_add_options_page') && current_user_can('manage_options')) {
        acf_add_options_page([
            'page_title' => __('Формы', 'wtw-translate'),
            'menu_title' => __('Формы', 'wtw-translate'),
            'menu_slug' => 'wtw_forms',
            'icon_url' => 'dashicons-screenoptions',
            'parent_slug' => 'tools.php',
            'update_button' => __('Update'),
            'updated_message' => __('Item updated.'),
            'autoload' => true,
        ]);
    }
});

if (!function_exists('reArrayFiles')) {
    function reArrayFiles(&$file_post)
    {
        if ($file_post === null) {
            return false;
        }
        $files_array = array();
        $file_count = count($file_post['name']);
        $file_keys = array_keys($file_post);
        for ($i = 0; $i < $file_count; $i++) {
            foreach ($file_keys as $key) {
                $files_array[$i][$key] = $file_post[$key][$i];
            }
        }
        return $files_array;
    }
}

// === 1. Генерация canvas капчи и уникального form_id ===
add_action('wp_footer', 'sfa_add_fields_via_js', 20);
function sfa_add_fields_via_js() {
    if (!session_id()) session_start();

    $extention_name = '__antispam';
    $extentions = wtw_forms_extentions();

    if (!isset($extentions[$extention_name])) {
        return;
    }

    $setup = $extentions[$extention_name][0];
    ?>
<script>
window.sfaCaptcha = {
    validate: async function(form) {
        const captchaInput = form.querySelector('input[name="sfa_captcha_answer"]');
        if (!captchaInput) return true;
        const captchaValue = captchaInput.value.trim();
        const parent = captchaInput.parentNode;
        let errorSpan = parent.querySelector('.just-validate-error-label');

        if (errorSpan) {
            errorSpan.textContent = '';
        } else {
            errorSpan = document.createElement('span');
            errorSpan.className = 'just-validate-error-label';
            parent.insertBefore(errorSpan, captchaInput.nextSibling);
        }

        try {
            const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'check_captcha',
                    sfa_captcha_answer: captchaValue,
                    form_id: form.getAttribute('data-form-id')
                })
            });
            const data = await response.json();
            if (!data.success) {
                const span = document.createElement('span');
                span.className = 'just-validate-error-label';
                span.textContent = '<?php echo $setup['message_for_code'] ?>';
                captchaInput.insertAdjacentElement('afterend', span);
                captchaInput.value = '';
                const canvas = form.querySelector('canvas');
                if (canvas) canvas.click();
                return false;
            }
            return true;
        } catch (error) {
            console.error('Captcha validation error:', error);
            const span = document.createElement('span');
            span.className = 'just-validate-error-label';
            span.textContent = '<?php echo $setup['message_for_error'] ?>';
            captchaInput.insertAdjacentElement('afterend', span);
            return false;
        }
    }
};
document.addEventListener('DOMContentLoaded', function() {

    document.querySelectorAll('form').forEach(form => {

        // === Уникальный ID формы ===
        let formId = form.getAttribute('data-form-id');
        if (!formId) {
            formId = 'form_' + Math.random().toString(36).substring(2,12);
            form.setAttribute('data-form-id', formId);
        }
        
        const captchaInput = form.querySelector('input[name="sfa_captcha_answer"]');
        if (captchaInput) {
            captchaInput.dataset.formId = formId;
        }

        // === Honeypot ===
        if (!form.querySelector('input[name="email_confirm"]')) {
            const honeypot = document.createElement('input');
            honeypot.type = 'text';
            honeypot.name = 'email_confirm';
            honeypot.style.display = 'none';
            honeypot.autocomplete = 'off';
            form.appendChild(honeypot);
        }

        // === Таймер ===
        if (!form.querySelector('input[name="form_time"]')) {
            const timer = document.createElement('input');
            timer.type = 'hidden';
            timer.name = 'form_time';
            timer.value = Math.floor(Date.now() / 1000);
            form.appendChild(timer);
        }

        // === Canvas капчи ===
        const captchaContainer = form.querySelector('[data-captcha]');
        if (captchaContainer && !captchaContainer.querySelector('canvas')) {
            const canvas = document.createElement('canvas');
            canvas.width = 160;
            canvas.height = 50;
            canvas.style.display = 'block';
            captchaContainer.appendChild(canvas);
        
            // === Кнопка обновления символов ===
            const refresh = document.createElement('div');
            refresh.textContent = '<?php echo $setup['update_text'] ?>';
            refresh.style.cursor = 'pointer';
            refresh.style.marginTop = '2px';
            refresh.style.fontSize = '13px';
            refresh.style.color = '<?php echo $setup['button_color'] ?>';
            refresh.style.userSelect = 'none';

            captchaContainer.appendChild(refresh);
        
            function generateCaptcha() {
                const letters = '<?php echo $setup['captcha_chars'] ?>';

                const length = Math.floor(Math.random() * 2) + 6; // 6-7 букв
                let word = '';
                for (let i = 0; i < length; i++) {
                    word += letters[Math.floor(Math.random() * letters.length)];
                }
        
                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'sfa_set_captcha',
                        word: word,
                        form_id: formId
                    })
                });
        
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '<?php echo $setup['bg_color'] ?>';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
        
                const colors = ['#007BFF','#FF4136','#2ECC40','#FF851B','#39CCCC','#F012BE','#01FF70','#FFDC00','#ff8b00','#0bebe8','#85144B','#FF69B4'];
                const spacing = canvas.width / (word.length + 1);
        
                for (let i = 0; i < word.length; i++) {
                    const char = word[i];
                    const isUpper = char === char.toUpperCase() && /[А-ЯЁ]/.test(char);
                    ctx.font = isUpper ? 'bold 28px Arial, sans-serif' : 'normal 22px Arial, sans-serif';
                    ctx.fillStyle = colors[Math.floor(Math.random() * colors.length)];
                    ctx.textBaseline = 'middle';
                    ctx.fillText(char, spacing * (i + 0.5), 25 + Math.random()*20);
                }
        
                for (let i = 0; i < 10; i++) {
                    ctx.fillStyle = colors[Math.floor(Math.random() * colors.length)];
                    ctx.beginPath();
                    ctx.arc(Math.random() * canvas.width, Math.random() * canvas.height, 1 + Math.random()*2, 0, Math.PI*2);
                    ctx.fill();
                }
        
                for (let i = 0; i < 3; i++) {
                    ctx.strokeStyle = colors[Math.floor(Math.random() * colors.length)];
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(Math.random()*canvas.width, Math.random()*canvas.height);
                    ctx.lineTo(Math.random()*canvas.width, Math.random()*canvas.height);
                    ctx.stroke();
                }
            }
        
            generateCaptcha();
        
            canvas.addEventListener('click', generateCaptcha);
            refresh.addEventListener('click', generateCaptcha);
        }       

    });
});
</script>
<?php
}

// === AJAX для установки ключа капчи с привязкой к form_id ===
add_action('wp_ajax_sfa_set_captcha', 'sfa_set_captcha');
add_action('wp_ajax_nopriv_sfa_set_captcha', 'sfa_set_captcha');
function sfa_set_captcha() {
    if (!session_id()) session_start();
    $word = $_POST['word'] ?? '';
    $form_id = $_POST['form_id'] ?? '';
    if ($form_id) {
        $_SESSION['sfa_captcha_keys'][$form_id] = $word;
    }
    wp_send_json_success();
}

// === AJAX для проверки капчи через JustValidate по form_id ===
add_action('wp_ajax_check_captcha', 'check_captcha');
add_action('wp_ajax_nopriv_check_captcha', 'check_captcha');
function check_captcha() {
    if (!session_id()) session_start();
    $answer = trim($_POST['sfa_captcha_answer'] ?? '');
  $form_id = $_POST['form_id'] ?? '';
  $key = $_SESSION['sfa_captcha_keys'][$form_id] ?? '';
  $valid = mb_strtolower($answer, 'UTF-8') === mb_strtolower($key, 'UTF-8');

    if ($valid) unset($_SESSION['sfa_captcha_keys'][$form_id]);
    wp_send_json(['success' => $valid]);
}

// === Проверка при отправке формы (Honeypot + таймер) ===
add_action('init', 'sfa_check_antispam');
function sfa_check_antispam() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!session_id()) session_start();

    if (!empty($_POST['email_confirm'])) wp_die('Спам обнаружен.');

    if (isset($_POST['form_time'])) {
        $diff = time() - (int)$_POST['form_time'];
        if ($diff < 3) wp_die('Слишком быстро, похоже на бота.');
    }
}
?><?php

add_action( 'acf/include_fields', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
	'key' => 'group_forms_59089a20ba7d8',
	'title' => 'Формы',
	'fields' => array(
		array(
			'key' => 'field_forms_663dcc562ee12',
			'label' => 'Формы',
			'name' => '',
			'aria-label' => '',
			'type' => 'tab',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'placement' => 'top',
			'endpoint' => 0,
			'selected' => 0,
		),
		array(
			'key' => 'field_forms_593406b27e390',
			'label' => 'Формы',
			'name' => 'wtw_forms',
			'aria-label' => '',
			'type' => 'repeater',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'layout' => 'block',
			'pagination' => 0,
			'min' => 0,
			'max' => 0,
			'collapsed' => 'field_forms_6631cd5cdf89f',
			'button_label' => 'Добавить настройку',
			'rows_per_page' => 20,
			'acfe_repeater_stylised_button' => 0,
			'sub_fields' => array(
				array(
					'key' => 'field_6661d91198cf5',
					'label' => 'Включить отправку',
					'name' => 'enabled',
					'aria-label' => '',
					'type' => 'true_false',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 1,
					'ui_on_text' => '',
					'ui_off_text' => '',
					'ui' => 1,
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_6631cd5cdf89f',
					'label' => 'Название настройки',
					'name' => 'desc',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 1,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '50',
						'class' => '',
						'id' => '',
					),
					'default_value' => 'Все формы',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_593437c01afba',
					'label' => 'Селектор отбора',
					'name' => 'selector',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 1,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '50',
						'class' => '',
						'id' => '',
					),
					'default_value' => 'form',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => 'селектор',
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_593406fb7e391',
					'label' => 'Email получателя',
					'name' => 'email',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 1,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => 'разделять запятой',
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_5934076d7e392',
					'label' => 'Тема письма',
					'name' => 'subject',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 1,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => 'Новое сообщение с сайта {{ __site }}',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_5934077e7e393',
					'label' => 'Сообщение',
					'name' => 'message',
					'aria-label' => '',
					'type' => 'textarea',
					'instructions' => '',
					'required' => 1,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => 'Данные формы:
{{ __fields }}

Страница: <b>{{ __page }}</b>
Заголовок: <b>{{ __title }}</b>
Форма: <b>{{ __form }}</b>
Запрос: <b>{{ __query }}</b>
Браузер: <b>{{ __browser }}</b>
IP: <b>{{ __ip }}</b>',
					'maxlength' => '',
					'rows' => 10,
					'placeholder' => '',
					'new_lines' => 'br',
					'parent_repeater' => 'field_forms_593406b27e390',
					'acfe_textarea_code' => 0,
				),
				array(
					'key' => 'field_6791b3dbe8db3',
					'label' => 'Показывать строки с пустыми значениями полей',
					'name' => 'show_empty_fields',
					'aria-label' => '',
					'type' => 'true_false',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 0,
					'ui_on_text' => '',
					'ui_off_text' => '',
					'ui' => 1,
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_59343c0f18dbc',
					'label' => 'Адрес для ответа',
					'name' => 'addreply',
					'aria-label' => '',
					'type' => 'email',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '50',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_593409615f453',
					'label' => 'Имя отправителя',
					'name' => 'from',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '50',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_593409945f454',
					'label' => 'Копия письма (CC)',
					'name' => 'cc',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '50',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_593409c15f455',
					'label' => 'Скрытая копия (BCC)',
					'name' => 'bcc',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '50',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_59340fc4b1930',
					'label' => 'Сообщение об отправке',
					'name' => 'success_message',
					'aria-label' => '',
					'type' => 'textarea',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_forms_663dd8ebbd8b8',
								'operator' => '!=',
								'value' => '1',
							),
						),
					),
					'wrapper' => array(
						'width' => '50',
						'class' => '',
						'id' => '',
					),
					'default_value' => 'Ваше сообщение отправлено!
Спасибо за обращение.',
					'maxlength' => '',
					'rows' => 2,
					'placeholder' => '',
					'new_lines' => 'br',
					'parent_repeater' => 'field_forms_593406b27e390',
					'acfe_textarea_code' => 0,
				),
				array(
					'key' => 'field_forms_59341000b1931',
					'label' => 'Сообщение об ошибке',
					'name' => 'error_message',
					'aria-label' => '',
					'type' => 'textarea',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_forms_663dd8ebbd8b8',
								'operator' => '!=',
								'value' => '1',
							),
						),
					),
					'wrapper' => array(
						'width' => '50',
						'class' => '',
						'id' => '',
					),
					'default_value' => 'Ошибка отправки!
Попробуйте позже.',
					'maxlength' => '',
					'rows' => 2,
					'placeholder' => '',
					'new_lines' => 'br',
					'parent_repeater' => 'field_forms_593406b27e390',
					'acfe_textarea_code' => 0,
				),
				array(
					'key' => 'field_forms_59340e7668e14',
					'label' => 'Скрыть форму после отправки',
					'name' => 'hide',
					'aria-label' => '',
					'type' => 'true_false',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '25',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 0,
					'ui_on_text' => '',
					'ui_off_text' => '',
					'ui' => 1,
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_59340ebd68e15',
					'label' => 'Скрыть сообщение об отправке',
					'name' => 'delay',
					'aria-label' => '',
					'type' => 'number',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '25',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'min' => '',
					'max' => '',
					'placeholder' => '',
					'step' => '',
					'prepend' => '',
					'append' => 'секунд',
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_5934130ec534c',
					'label' => 'Скрыть блок после отправки',
					'name' => 'lbox_hide',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '25',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => 'селектор',
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_693829fc239e1',
					'label' => 'Длительность затухания',
					'name' => 'hide_duration',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '25',
						'class' => '',
						'id' => '',
					),
					'default_value' => 1000,
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => 'мс',
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_663dd8ebbd8b8',
					'label' => 'Редирект после отправки',
					'name' => 'redirect',
					'aria-label' => '',
					'type' => 'true_false',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '33',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 0,
					'ui_on_text' => '',
					'ui_off_text' => '',
					'ui' => 1,
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_59340f1168e16',
					'label' => 'Перенаправление на страницу',
					'name' => 'redirect_url',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_forms_663dd8ebbd8b8',
								'operator' => '==',
								'value' => '1',
							),
						),
					),
					'wrapper' => array(
						'width' => '33',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'parent_repeater' => 'field_forms_593406b27e390',
				),
				array(
					'key' => 'field_forms_59340e7668e15',
					'label' => 'Перенаправлять в новом окне',
					'name' => 'redirect_new_tab',
					'aria-label' => '',
					'type' => 'true_false',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_forms_663dd8ebbd8b8',
								'operator' => '==',
								'value' => '1',
							),
						),
					),
					'wrapper' => array(
						'width' => '30',
						'class' => '',
						'id' => '',
					),
					'message' => '',
					'default_value' => 0,
					'ui_on_text' => '',
					'ui_off_text' => '',
					'ui' => 1,
					'parent_repeater' => 'field_forms_593406b27e390',
				),
			),
		),
		array(
			'key' => 'field_forms_663dcc3b2ee11',
			'label' => 'Расширения',
			'name' => '',
			'aria-label' => '',
			'type' => 'tab',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'placement' => 'top',
			'endpoint' => 0,
			'selected' => 0,
		),
		array(
			'key' => 'field_forms_663dcc742ee13',
			'label' => 'Расширения',
			'name' => 'wtw_forms_extentions',
			'aria-label' => '',
			'type' => 'flexible_content',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'acfe_flexible_advanced' => 0,
			'layouts' => array(
				'layout_691e32044e7e4' => array(
					'key' => 'layout_691e32044e7e4',
					'name' => '__antispam',
					'label' => 'Антиспам',
					'display' => 'block',
					'sub_fields' => array(
						array(
							'key' => 'field_691e331917109',
							'label' => 'Включить антиспам',
							'name' => 'enabled',
							'aria-label' => '',
							'type' => 'true_false',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '',
							'default_value' => 0,
							'allow_in_bindings' => 0,
							'ui_on_text' => '',
							'ui_off_text' => '',
							'ui' => 1,
						),
						array(
							'key' => 'field_692047149582e',
							'label' => 'Обновить символы',
							'name' => 'update_text',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'default_value' => 'Обновить символы',
							'maxlength' => '',
							'allow_in_bindings' => 0,
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_691e32784e7e8',
							'label' => 'Ошибка проверки',
							'name' => 'message_for_error',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'default_value' => 'Ошибка проверки капчи. Пожалуйста, попробуйте еще раз.',
							'maxlength' => '',
							'allow_in_bindings' => 0,
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_691e328a4e7e9',
							'label' => 'Неверный код',
							'name' => 'message_for_code',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'default_value' => 'Неверный код с изображения',
							'maxlength' => '',
							'allow_in_bindings' => 0,
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_691e32244e7e6',
							'label' => 'Цвет кнопки',
							'name' => 'button_color',
							'aria-label' => '',
							'type' => 'color_picker',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => '#000000',
							'enable_opacity' => 0,
							'return_format' => 'string',
							'allow_in_bindings' => 0,
							'show_custom_palette' => 0,
							'show_color_wheel' => 1,
							'custom_palette_source' => '',
							'palette_colors' => '',
						),
						array(
							'key' => 'field_691e32554e7e7',
							'label' => 'Цвет фона',
							'name' => 'bg_color',
							'aria-label' => '',
							'type' => 'color_picker',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => '#ffffff',
							'enable_opacity' => 0,
							'return_format' => 'string',
							'allow_in_bindings' => 0,
							'show_custom_palette' => 0,
							'show_color_wheel' => 1,
							'custom_palette_source' => '',
							'palette_colors' => '',
						),
						array(
							'key' => 'field_69201ed4e05ca',
							'label' => 'Язык капчи',
							'name' => 'captcha_chars',
							'aria-label' => '',
							'type' => 'radio',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'choices' => array(
								'АБВГДЕЁЖЗИЙКЛМНПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнпрстуфхцчшщъыьэюя0123456789' => 'RU',
								'ABCDEFGHIJKLMNPQRSTUVWXYZabcdefghijklmnpqrstuvwxyz0123456789' => 'EN',
							),
							'default_value' => '',
							'return_format' => 'value',
							'allow_null' => 0,
							'other_choice' => 0,
							'allow_in_bindings' => 0,
							'layout' => 'vertical',
							'save_other_choice' => 0,
						),
					),
					'min' => '',
					'max' => '',
					'acfe_flexible_render_template' => false,
					'acfe_flexible_render_style' => false,
					'acfe_flexible_render_script' => false,
					'acfe_flexible_thumbnail' => false,
					'acfe_flexible_settings' => false,
					'acfe_flexible_settings_size' => false,
					'acfe_flexible_modal_edit_size' => false,
					'acfe_flexible_category' => false,
				),
				'layout_forms_663dcdada936c' => array(
					'key' => 'layout_forms_663dcdada936c',
					'name' => '__google_recaptcha',
					'label' => 'Google Recaptcha',
					'display' => 'block',
					'sub_fields' => array(
						array(
							'key' => 'field_forms_663dcdada936d',
							'label' => 'Включить',
							'name' => 'enabled',
							'aria-label' => '',
							'type' => 'true_false',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '',
							'default_value' => 0,
							'ui_on_text' => '',
							'ui_off_text' => '',
							'ui' => 1,
						),
						array(
							'key' => 'field_forms_663de480ca996',
							'label' => 'Отбор по селектору',
							'name' => 'selector',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'default_value' => 'form',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663dd171e47df',
							'label' => 'Ключ сайта',
							'name' => 'recaptcha_site_key',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663dd1b9e47e0',
							'label' => 'Секретный ключ',
							'name' => 'recaptcha_secret_key',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
					),
					'min' => '',
					'max' => '1',
					'acfe_flexible_render_template' => false,
					'acfe_flexible_render_style' => false,
					'acfe_flexible_render_script' => false,
					'acfe_flexible_thumbnail' => false,
					'acfe_flexible_settings' => false,
					'acfe_flexible_settings_size' => false,
					'acfe_flexible_modal_edit_size' => false,
					'acfe_flexible_category' => false,
				),
				'layout_forms_663dce0aa9370' => array(
					'key' => 'layout_forms_663dce0aa9370',
					'name' => '__validate',
					'label' => 'Валидация полей',
					'display' => 'block',
					'sub_fields' => array(
						array(
							'key' => 'field_forms_663dcfa0fd57a',
							'label' => 'Включить',
							'name' => 'enabled',
							'aria-label' => '',
							'type' => 'true_false',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '',
							'default_value' => 1,
							'ui_on_text' => '',
							'ui_off_text' => '',
							'ui' => 1,
						),
						array(
							'key' => 'field_forms_65b4ee68e7177',
							'label' => 'Добавить поле для валидации',
							'name' => 'validate_fields',
							'aria-label' => '',
							'type' => 'repeater',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'layout' => 'block',
							'min' => 0,
							'max' => 0,
							'collapsed' => 'field_forms_65b4eeade7178',
							'button_label' => 'Добавить поле',
							'rows_per_page' => 20,
							'acfe_repeater_stylised_button' => 0,
							'sub_fields' => array(
								array(
									'key' => 'field_forms_65b4eeade7178',
									'label' => 'Селектор поля',
									'name' => 'selector',
									'aria-label' => '',
									'type' => 'text',
									'instructions' => 'Класс, id или атрибут',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'default_value' => '',
									'maxlength' => '',
									'placeholder' => '',
									'prepend' => '',
									'append' => '',
									'parent_repeater' => 'field_forms_65b4ee68e7177',
								),
								array(
									'key' => 'field_forms_65b4f9aee7179',
									'label' => 'Добавить правило',
									'name' => 'rules',
									'aria-label' => '',
									'type' => 'repeater',
									'instructions' => '',
									'required' => 0,
									'conditional_logic' => 0,
									'wrapper' => array(
										'width' => '',
										'class' => '',
										'id' => '',
									),
									'layout' => 'block',
									'min' => 0,
									'max' => 0,
									'collapsed' => 'field_forms_65b4f9d8e717a',
									'button_label' => 'Добавить правило',
									'rows_per_page' => 20,
									'parent_repeater' => 'field_forms_65b4ee68e7177',
									'acfe_repeater_stylised_button' => 0,
									'sub_fields' => array(
										array(
											'key' => 'field_forms_65b4f9d8e717a',
											'label' => 'Правило',
											'name' => 'rule',
											'aria-label' => '',
											'type' => 'select',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'choices' => array(
												'required' => 'Обязательное поле',
												'email' => 'Валидация поля на наличие email',
												'minLength' => 'Минимальное количество символов',
												'maxLength' => 'Максимальное количество символов',
												'number' => 'Валидация поля на наличие числа',
												'integer' => 'Валидация поля на наличие целого числа',
												'minNumber' => 'Минимально допустимое для ввода число',
												'maxNumber' => 'Максимально допустимое для ввода число',
												'password' => 'Валидация поля на наличие пароля',
												'strongPassword' => 'Валидация поля на наличие строгого пароля',
												'customRegexp' => 'Пользовательское регулярное выражение',
												'files' => 'Валидация прикрепленных файлов',
												'minFilesCount' => 'Минимальное количество файлов',
												'maxFilesCount' => 'Максимальное количество файлов',
												'custom_code' => 'Своя формула',
											),
											'default_value' => false,
											'return_format' => 'value',
											'multiple' => 0,
											'allow_custom' => 0,
											'search_placeholder' => '',
											'allow_null' => 0,
											'ui' => 1,
											'ajax' => 0,
											'placeholder' => '',
											'create_options' => 0,
											'save_options' => 0,
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_forms_65b505dcd57b8',
											'label' => 'Текст оповещения об ошибке',
											'name' => 'error_message',
											'aria-label' => '',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'maxlength' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_forms_65b50215401d0',
											'label' => 'Минимальное число символов',
											'name' => 'min_length',
											'aria-label' => '',
											'type' => 'number',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => array(
												array(
													array(
														'field' => 'field_forms_65b4f9d8e717a',
														'operator' => '==contains',
														'value' => 'minLength',
													),
												),
											),
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'min' => '',
											'max' => '',
											'placeholder' => '',
											'step' => '',
											'prepend' => '',
											'append' => '',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_forms_65b50234401d1',
											'label' => 'Максимальное число символов',
											'name' => 'max_length',
											'aria-label' => '',
											'type' => 'number',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => array(
												array(
													array(
														'field' => 'field_forms_65b4f9d8e717a',
														'operator' => '==contains',
														'value' => 'maxLength',
													),
												),
											),
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'min' => '',
											'max' => '',
											'placeholder' => '',
											'step' => '',
											'prepend' => '',
											'append' => '',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_forms_65b5029822614',
											'label' => 'Минимальное число',
											'name' => 'min_number',
											'aria-label' => '',
											'type' => 'number',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => array(
												array(
													array(
														'field' => 'field_forms_65b4f9d8e717a',
														'operator' => '==contains',
														'value' => 'minNumber',
													),
												),
											),
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'min' => '',
											'max' => '',
											'placeholder' => '',
											'step' => '',
											'prepend' => '',
											'append' => '',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_forms_65b502cd22615',
											'label' => 'Максимальное число',
											'name' => 'max_number',
											'aria-label' => '',
											'type' => 'number',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => array(
												array(
													array(
														'field' => 'field_forms_65b4f9d8e717a',
														'operator' => '==contains',
														'value' => 'maxNumber',
													),
												),
											),
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'min' => '',
											'max' => '',
											'placeholder' => '',
											'step' => '',
											'prepend' => '',
											'append' => '',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_forms_65b502fb22616',
											'label' => 'Регулярное выражение',
											'name' => 'custom_regexp',
											'aria-label' => '',
											'type' => 'text',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => array(
												array(
													array(
														'field' => 'field_forms_65b4f9d8e717a',
														'operator' => '==contains',
														'value' => 'customRegexp',
													),
												),
											),
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'maxlength' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_forms_65b5032e22617',
											'label' => 'Минимальное количество файлов',
											'name' => 'min_files_count',
											'aria-label' => '',
											'type' => 'number',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => array(
												array(
													array(
														'field' => 'field_forms_65b4f9d8e717a',
														'operator' => '==contains',
														'value' => 'minFilesCount',
													),
												),
											),
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'min' => '',
											'max' => '',
											'placeholder' => '',
											'step' => '',
											'prepend' => '',
											'append' => '',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_forms_65b5034b22618',
											'label' => 'Максимальное количество файлов',
											'name' => 'max_files_count',
											'aria-label' => '',
											'type' => 'number',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => array(
												array(
													array(
														'field' => 'field_forms_65b4f9d8e717a',
														'operator' => '==contains',
														'value' => 'maxFilesCount',
													),
												),
											),
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'min' => '',
											'max' => '',
											'placeholder' => '',
											'step' => '',
											'prepend' => '',
											'append' => '',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_forms_65b5039022619',
											'label' => 'Допустимые форматы файлов',
											'name' => 'extensions',
											'aria-label' => '',
											'type' => 'text',
											'instructions' => 'Форматы файлов указывать в одинарных кавычках через запятую, пример: \'jpeg\', \'jpg\', \'png\'',
											'required' => 1,
											'conditional_logic' => array(
												array(
													array(
														'field' => 'field_forms_65b4f9d8e717a',
														'operator' => '==contains',
														'value' => 'files',
													),
												),
											),
											'wrapper' => array(
												'width' => '50',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'maxlength' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_forms_65b504682261a',
											'label' => 'Допустимые форматы файлов в формате MIME types',
											'name' => 'types',
											'aria-label' => '',
											'type' => 'text',
											'instructions' => 'Форматы файлов указывать в одинарных кавычках через запятую, пример: \'image/jpeg\', \'image/jpg\', \'image/png\'. <a target="_blank" href="https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types">Перечень MIME types</a>',
											'required' => 1,
											'conditional_logic' => array(
												array(
													array(
														'field' => 'field_forms_65b4f9d8e717a',
														'operator' => '==contains',
														'value' => 'files',
													),
												),
											),
											'wrapper' => array(
												'width' => '50',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'maxlength' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_forms_65b50566d57b6',
											'label' => 'Минимальный размер файла в байтах',
											'name' => 'min_size',
											'aria-label' => '',
											'type' => 'number',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => array(
												array(
													array(
														'field' => 'field_forms_65b4f9d8e717a',
														'operator' => '==contains',
														'value' => 'files',
													),
												),
											),
											'wrapper' => array(
												'width' => '25',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'min' => '',
											'max' => '',
											'placeholder' => '',
											'step' => '',
											'prepend' => '',
											'append' => '',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_forms_65b505bad57b7',
											'label' => 'Максимальный размер файла в байтах',
											'name' => 'max_size',
											'aria-label' => '',
											'type' => 'number',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => array(
												array(
													array(
														'field' => 'field_forms_65b4f9d8e717a',
														'operator' => '==contains',
														'value' => 'files',
													),
												),
											),
											'wrapper' => array(
												'width' => '25',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'min' => '',
											'max' => '',
											'placeholder' => '',
											'step' => '',
											'prepend' => '',
											'append' => '',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
										array(
											'key' => 'field_6916cbcf74270',
											'label' => 'Произвольный код',
											'name' => 'custom_code',
											'aria-label' => '',
											'type' => 'acf_code_field',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => array(
												array(
													array(
														'field' => 'field_forms_65b4f9d8e717a',
														'operator' => '==',
														'value' => 'custom_code',
													),
												),
											),
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'placeholder' => '',
											'mode' => 'htmlmixed',
											'theme' => 'monokai',
											'parent_repeater' => 'field_forms_65b4f9aee7179',
										),
									),
								),
							),
						),
						array(
							'key' => 'field_forms_65b4fd551da98',
							'label' => 'Обзор правил',
							'name' => '',
							'aria-label' => '',
							'type' => 'message',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '<blockquote style="background: #EAECF0; padding: 5px; margin-bottom: 10px; font-weight: 600">Обратите внимание, правила не вызывают ошибку проверки, если указанное поле не будет заполнено.
		В противном случае следует выбрать параметр "Обязательное поле"</blockquote>

<div style="padding: 8px">
		<strong>Селектор поля</strong> — указывает на то, к какому полю подключатся текущие правила.

		Можно указать:
		<strong>form</strong> - все формы;
		<strong>.class</strong> класс поля;
		<strong>#id</strong> идентификатор поля;
		<strong>[attribute=value]</strong> атрибут поля
</div>
<div style="background: #EAECF0; padding: 8px">
		<strong>Обязательное поле</strong> — делает поле обязательным. Наличие атрибута required на самом поле не обязательно.
</div>
<div style="padding: 8px">
		<strong>Валидация поля на наличие email</strong> — в поле можно ввести только email адрес
</div>
<div style="background: #EAECF0; padding: 8px">
		<strong>Минимальное количество символов</strong> — ограничивает минимальную длину текста
</div>
<div style="padding: 8px">
		<strong>Максимальное количество символов</strong> — ограничивает максимальную длину текста
</div>
<div style="background: #EAECF0; padding: 8px">
		<strong>Валидация поля на наличие числа</strong> — ограничивает ввод числом (целым или с плавающей точкой)
</div>
<div style="padding: 8px">
		<strong>Валидация поля на наличие целого числа</strong> — ограничивает ввод целым числом
</div>
<div style="background: #EAECF0; padding: 8px">
		<strong>Минимально допустимое для ввода число</strong> — указанное в поле ввода число должно быть больше заданного значения
</div>
<div style="padding: 8px">
		<strong>Максимально допустимое для ввода число</strong> — указанное в поле ввода число должно быть меньше заданного значения
</div>
<div style="background: #EAECF0; padding: 8px">
		<strong>Валидация поля на наличие пароля</strong> — минимум восемь символов, по крайней мере одна буква и одна цифра.
</div>
<div style="padding: 8px">
		<strong>Валидация поля на наличие строгого пароля</strong> — минимум восемь символов, по крайней мере одна заглавная буква, одна строчная буква, одна цифра и один специальный символ.
</div>
<div style="background: #EAECF0; padding: 8px">
		<strong>Пользовательское регулярное выражение</strong> — возможность указать свое регулярное выражения для валидации поля
</div>
<div style="padding: 8px">
		<strong>Валидация прикрепленных файлов</strong> — атрибуты загруженных файлов должны соответствовать конфигурации указанных значений. Среди обязательных атрибутов находятся допустимые разрешения файлов и они же в формате MIME types, минимальный и максимальный размер файлов.
</div>
<div style="background: #EAECF0; padding: 8px">
		<strong>Минимальное количество файлов</strong> — ограничивает добавление файлов минимально указанным количеством
</div>
<div style="padding: 8px">
		<strong>Максимальное количество файлов</strong> — ограничивает добавление файлов максимально указанным количеством
</div>
<div style="background: #EAECF0; padding: 8px">
		<strong>Текст оповещения об ошибке</strong> — всплывающее оповещение об ошибке валидации поля, к которому относится текущее правило.
		<br><br>
		Настроить вид всплывающего поля можно через класс <strong>just-validate-error-label</strong>.
		Так же можно настроить стили состояния самого поля для успешной и не успешной валидации:<br>
		<strong>just-validate-error-field</strong> — класс поля неудачной валидации;<br>
		<strong>just-validate-success-field</strong> — класс поля удачной валидации;<br>
</div>',
							'new_lines' => 'wpautop',
							'esc_html' => 0,
						),
					),
					'min' => '',
					'max' => '1',
					'acfe_flexible_render_template' => false,
					'acfe_flexible_render_style' => false,
					'acfe_flexible_render_script' => false,
					'acfe_flexible_thumbnail' => false,
					'acfe_flexible_settings' => false,
					'acfe_flexible_settings_size' => false,
					'acfe_flexible_modal_edit_size' => false,
					'acfe_flexible_category' => false,
				),
				'layout_forms_663dcc7bb3995' => array(
					'key' => 'layout_forms_663dcc7bb3995',
					'name' => '__sender_reply',
					'label' => 'Ответ отправителю',
					'display' => 'block',
					'sub_fields' => array(
						array(
							'key' => 'field_forms_59340ccbbd521',
							'label' => 'Включить',
							'name' => 'enabled',
							'aria-label' => '',
							'type' => 'true_false',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '',
							'default_value' => 1,
							'ui_on_text' => '',
							'ui_off_text' => '',
							'ui' => 1,
						),
						array(
							'key' => 'field_forms_663de4d3ca998',
							'label' => 'Отбор по селектору',
							'name' => 'selector',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'default_value' => 'form',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663dd07800b6e',
							'label' => 'Название поля email',
							'name' => 'reply_email',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'default_value' => 'Email',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663dd08500b6f',
							'label' => 'Тема письма',
							'name' => 'reply_subject',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663dd09e00b70',
							'label' => 'Текcт письма',
							'name' => 'reply_message',
							'aria-label' => '',
							'type' => 'textarea',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'rows' => '',
							'placeholder' => '',
							'new_lines' => 'br',
							'acfe_textarea_code' => 0,
						),
						array(
							'key' => 'field_6791b43be8db4',
							'label' => 'Показывать строки с пустыми значениями полей',
							'name' => 'show_empty_fields',
							'aria-label' => '',
							'type' => 'true_false',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '',
							'default_value' => 0,
							'ui_on_text' => '',
							'ui_off_text' => '',
							'ui' => 1,
						),
						array(
							'key' => 'field_forms_663dd0b000b71',
							'label' => 'Файл для отправки',
							'name' => 'reply_file',
							'aria-label' => '',
							'type' => 'file',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'return_format' => 'url',
							'library' => 'all',
							'min_size' => '',
							'max_size' => '',
							'mime_types' => '',
							'uploader' => '',
						),
					),
					'min' => '',
					'max' => '',
					'acfe_flexible_render_template' => false,
					'acfe_flexible_render_style' => false,
					'acfe_flexible_render_script' => false,
					'acfe_flexible_thumbnail' => false,
					'acfe_flexible_settings' => false,
					'acfe_flexible_settings_size' => false,
					'acfe_flexible_modal_edit_size' => false,
					'acfe_flexible_category' => false,
				),
				'layout_forms_663dcd3da936a' => array(
					'key' => 'layout_forms_663dcd3da936a',
					'name' => '__smtp',
					'label' => 'Отправка через SMTP',
					'display' => 'block',
					'sub_fields' => array(
						array(
							'key' => 'field_forms_663dcd3da936b',
							'label' => 'Включить',
							'name' => 'enabled',
							'aria-label' => '',
							'type' => 'true_false',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '',
							'default_value' => 1,
							'ui_on_text' => '',
							'ui_off_text' => '',
							'ui' => 1,
						),
						array(
							'key' => 'field_forms_663dedf4545ce',
							'label' => 'Сервер SMTP',
							'name' => 'smtp_server',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => 'smtp.yandex.ru',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663dee47545d1',
							'label' => 'Порт',
							'name' => 'smtp_port',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => 465,
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663dee0c545cf',
							'label' => 'Имя пользователя (email)',
							'name' => 'smtp_user',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663dee2c545d0',
							'label' => 'Пароль',
							'name' => 'smtp_password',
							'aria-label' => '',
							'type' => 'password',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_6937d507d80ee',
							'label' => 'Имя отправителя',
							'name' => 'sender_name',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_6937d536d80ef',
							'label' => 'Email отправителя',
							'name' => 'sender_email',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '49',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663defbf545d3',
							'label' => 'Авторизация',
							'name' => 'smtp_auth',
							'aria-label' => '',
							'type' => 'true_false',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '33',
								'class' => '',
								'id' => '',
							),
							'message' => '',
							'default_value' => 1,
							'ui_on_text' => '',
							'ui_off_text' => '',
							'ui' => 1,
						),
						array(
							'key' => 'field_forms_663dee5b545d2',
							'label' => 'Защита',
							'name' => 'smtp_secure',
							'aria-label' => '',
							'type' => 'button_group',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '33',
								'class' => '',
								'id' => '',
							),
							'choices' => array(
								'ssl' => 'SSL',
								'tls' => 'TLS',
							),
							'default_value' => 'ssl',
							'return_format' => 'value',
							'allow_null' => 0,
							'layout' => 'horizontal',
						),
						array(
							'key' => 'field_forms_663df009545d4',
							'label' => 'Кодировка',
							'name' => 'smtp_encoding',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '33',
								'class' => '',
								'id' => '',
							),
							'default_value' => 'utf-8',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
					),
					'min' => '',
					'max' => '1',
					'acfe_flexible_render_template' => false,
					'acfe_flexible_render_style' => false,
					'acfe_flexible_render_script' => false,
					'acfe_flexible_thumbnail' => false,
					'acfe_flexible_settings' => false,
					'acfe_flexible_settings_size' => false,
					'acfe_flexible_modal_edit_size' => false,
					'acfe_flexible_category' => false,
				),
				'layout_forms_663dcde3a936e' => array(
					'key' => 'layout_forms_663dcde3a936e',
					'name' => '__telegram',
					'label' => 'Отправка в Телеграм',
					'display' => 'block',
					'sub_fields' => array(
						array(
							'key' => 'field_forms_663de54eca99a',
							'label' => 'Включить',
							'name' => 'enabled',
							'aria-label' => '',
							'type' => 'true_false',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '',
							'default_value' => 1,
							'ui_on_text' => '',
							'ui_off_text' => '',
							'ui' => 1,
						),
						array(
							'key' => 'field_forms_663de4fdca999',
							'label' => 'Отбор по селектору',
							'name' => 'selector',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'default_value' => 'form',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663df22f7715a',
							'label' => 'Токен бота',
							'name' => 'token',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663df2407715b',
							'label' => 'ID группы / пользователя',
							'name' => 'chat_id',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663df2567715c',
							'label' => 'Шаблон отправки',
							'name' => 'template',
							'aria-label' => '',
							'type' => 'textarea',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'default_value' => 'Отправка формы с сайта:
{{ __fields }}',
							'maxlength' => '',
							'rows' => '',
							'placeholder' => '',
							'new_lines' => '',
							'acfe_textarea_code' => 0,
						),
						array(
							'key' => 'field_6791b52f28c06',
							'label' => 'Показывать строки с пустыми значениями полей',
							'name' => 'show_empty_fields',
							'aria-label' => '',
							'type' => 'true_false',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '',
							'default_value' => 0,
							'ui_on_text' => '',
							'ui_off_text' => '',
							'ui' => 1,
						),
						array(
							'key' => 'field_6937d5f0c0e4e',
							'label' => 'Отправлять файлы',
							'name' => 'send_files',
							'aria-label' => '',
							'type' => 'true_false',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '',
							'default_value' => 0,
							'ui_on_text' => '',
							'ui_off_text' => '',
							'ui' => 1,
						),
					),
					'min' => '',
					'max' => '',
					'acfe_flexible_render_template' => false,
					'acfe_flexible_render_style' => false,
					'acfe_flexible_render_script' => false,
					'acfe_flexible_thumbnail' => false,
					'acfe_flexible_settings' => false,
					'acfe_flexible_settings_size' => false,
					'acfe_flexible_modal_edit_size' => false,
					'acfe_flexible_category' => false,
				),
				'layout_forms_663dce43a9371' => array(
					'key' => 'layout_forms_663dce43a9371',
					'name' => '__bitrix',
					'label' => 'Отправка в Bitrix24',
					'display' => 'block',
					'sub_fields' => array(
						array(
							'key' => 'field_forms_663de5bbe32e3',
							'label' => 'Включить',
							'name' => 'enabled',
							'aria-label' => '',
							'type' => 'true_false',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '',
							'default_value' => 1,
							'ui_on_text' => '',
							'ui_off_text' => '',
							'ui' => 1,
						),
						array(
							'key' => 'field_forms_663de576ca99b',
							'label' => 'Отбор по селектору',
							'name' => 'selector',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => 'form',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663df4615132a',
							'label' => 'Секретная cсылка API',
							'name' => 'bitrix_api_url',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_forms_663df4fb5132b',
							'label' => 'Создать в BITRIX24',
							'name' => 'entities',
							'aria-label' => '',
							'type' => 'flexible_content',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'acfe_flexible_advanced' => 0,
							'layouts' => array(
								'layout_681b8eff52cea' => array(
									'key' => 'layout_681b8eff52cea',
									'name' => 'lead',
									'label' => 'Лид',
									'display' => 'block',
									'sub_fields' => array(
										array(
											'key' => 'field_681b8eff52ceb',
											'label' => 'Заголовок',
											'name' => 'title',
											'aria-label' => '',
											'type' => 'text',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => 'Новая сделка с сайта {{ site }}',
											'maxlength' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
										),
										array(
											'key' => 'field_681b8eff52cec',
											'label' => 'Поля лида',
											'name' => 'fields',
											'aria-label' => '',
											'type' => 'repeater',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'layout' => 'block',
											'min' => 0,
											'max' => 0,
											'collapsed' => '',
											'button_label' => 'Добавить поле',
											'rows_per_page' => 20,
											'acfe_repeater_stylised_button' => 0,
											'sub_fields' => array(
												array(
													'key' => 'field_681b8eff52ced',
													'label' => 'Название поля',
													'name' => 'name',
													'aria-label' => '',
													'type' => 'select',
													'instructions' => '',
													'required' => 1,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '25',
														'class' => '',
														'id' => '',
													),
													'choices' => array(
														'ASSIGNED_BY_ID' => 'Ответственный',
														'CATEGORY_ID' => 'Воронка',
														'STAGE_ID' => 'Этап сделки',
														'SOURCE_ID' => 'Источник',
														'OPPORTUNITY' => 'Сумма',
														'PHONE' => 'Телефон',
														'EMAIL' => 'Email',
														'CUSTOM' => 'Произвольное поле',
													),
													'default_value' => false,
													'return_format' => 'value',
													'multiple' => 0,
													'allow_custom' => 0,
													'search_placeholder' => '',
													'allow_null' => 0,
													'ui' => 1,
													'ajax' => 0,
													'placeholder' => '',
													'parent_repeater' => 'field_681b8eff52cec',
												),
												array(
													'key' => 'field_681b8eff52cee',
													'label' => 'Произвольное поле',
													'name' => 'custom_field',
													'aria-label' => '',
													'type' => 'text',
													'instructions' => '',
													'required' => 0,
													'conditional_logic' => array(
														array(
															array(
																'field' => 'field_681b8eff52ced',
																'operator' => '==',
																'value' => 'CUSTOM',
															),
														),
													),
													'wrapper' => array(
														'width' => '25',
														'class' => '',
														'id' => '',
													),
													'default_value' => '',
													'maxlength' => '',
													'placeholder' => '',
													'prepend' => '',
													'append' => '',
													'parent_repeater' => 'field_681b8eff52cec',
												),
												array(
													'key' => 'field_681b8eff52cef',
													'label' => 'Значение поля',
													'name' => 'value',
													'aria-label' => '',
													'type' => 'text',
													'instructions' => '',
													'required' => 1,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '25',
														'class' => '',
														'id' => '',
													),
													'default_value' => '',
													'maxlength' => '',
													'placeholder' => '',
													'prepend' => '',
													'append' => '',
													'parent_repeater' => 'field_681b8eff52cec',
												),
												array(
													'key' => 'field_6933238efe90b',
													'label' => 'Тип',
													'name' => 'type',
													'aria-label' => '',
													'type' => 'select',
													'instructions' => '',
													'required' => 0,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '25',
														'class' => '',
														'id' => '',
													),
													'choices' => array(
														'text' => 'Текст',
														'number' => 'Число',
														'file' => 'Файл',
														'array' => 'Массив',
													),
													'default_value' => false,
													'return_format' => 'value',
													'multiple' => 0,
													'allow_null' => 0,
													'ui' => 0,
													'ajax' => 0,
													'placeholder' => '',
													'allow_custom' => 0,
													'search_placeholder' => '',
													'parent_repeater' => 'field_681b8eff52cec',
												),
											),
										),
									),
									'min' => '',
									'max' => '1',
									'acfe_flexible_render_template' => false,
									'acfe_flexible_render_style' => false,
									'acfe_flexible_render_script' => false,
									'acfe_flexible_thumbnail' => false,
									'acfe_flexible_settings' => false,
									'acfe_flexible_settings_size' => false,
									'acfe_flexible_modal_edit_size' => false,
									'acfe_flexible_category' => false,
								),
								'layout_forms_663df65851331' => array(
									'key' => 'layout_forms_663df65851331',
									'name' => 'deal',
									'label' => 'Сделка',
									'display' => 'block',
									'sub_fields' => array(
										array(
											'key' => 'field_forms_663df65851332',
											'label' => 'Заголовок',
											'name' => 'title',
											'aria-label' => '',
											'type' => 'text',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => 'Новая сделка с сайта {{ site }}',
											'maxlength' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
										),
										array(
											'key' => 'field_forms_66604fa407aa6',
											'label' => 'Поля сделки',
											'name' => 'fields',
											'aria-label' => '',
											'type' => 'repeater',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'layout' => 'block',
											'min' => 0,
											'max' => 0,
											'collapsed' => 'field_forms_66604fb507aa7',
											'button_label' => 'Добавить поле',
											'rows_per_page' => 20,
											'acfe_repeater_stylised_button' => 0,
											'sub_fields' => array(
												array(
													'key' => 'field_668e62211dc51',
													'label' => 'Название поля',
													'name' => 'name',
													'aria-label' => '',
													'type' => 'select',
													'instructions' => '',
													'required' => 1,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '25',
														'class' => '',
														'id' => '',
													),
													'choices' => array(
														'ASSIGNED_BY_ID' => 'Ответственный',
														'CATEGORY_ID' => 'Воронка',
														'STAGE_ID' => 'Этап сделки',
														'SOURCE_ID' => 'Источник',
														'PHONE' => 'Телефон',
														'EMAIL' => 'Email',
														'OPPORTUNITY' => 'Сумма',
														'CUSTOM' => 'Произвольное поле',
													),
													'default_value' => false,
													'return_format' => 'value',
													'multiple' => 0,
													'allow_custom' => 0,
													'search_placeholder' => '',
													'allow_null' => 0,
													'ui' => 1,
													'ajax' => 0,
													'placeholder' => '',
													'parent_repeater' => 'field_forms_66604fa407aa6',
												),
												array(
													'key' => 'field_forms_6661432b2de83',
													'label' => 'Произвольное поле',
													'name' => 'custom_field',
													'aria-label' => '',
													'type' => 'text',
													'instructions' => '',
													'required' => 0,
													'conditional_logic' => array(
														array(
															array(
																'field' => 'field_668e62211dc51',
																'operator' => '==',
																'value' => 'CUSTOM',
															),
														),
													),
													'wrapper' => array(
														'width' => '25',
														'class' => '',
														'id' => '',
													),
													'default_value' => '',
													'maxlength' => '',
													'placeholder' => '',
													'prepend' => '',
													'append' => '',
													'parent_repeater' => 'field_forms_66604fa407aa6',
												),
												array(
													'key' => 'field_forms_666050a407aa8',
													'label' => 'Значение поля',
													'name' => 'value',
													'aria-label' => '',
													'type' => 'text',
													'instructions' => '',
													'required' => 1,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '25',
														'class' => '',
														'id' => '',
													),
													'default_value' => '',
													'maxlength' => '',
													'placeholder' => '',
													'prepend' => '',
													'append' => '',
													'parent_repeater' => 'field_forms_66604fa407aa6',
												),
												array(
													'key' => 'field_693326666b0dd',
													'label' => 'Тип',
													'name' => 'type',
													'aria-label' => '',
													'type' => 'select',
													'instructions' => '',
													'required' => 0,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '',
														'class' => '',
														'id' => '',
													),
													'choices' => array(
														'text' => 'Текст',
														'number' => 'Число',
														'file' => 'Файл',
														'array' => 'Массив',
													),
													'default_value' => false,
													'return_format' => 'value',
													'multiple' => 0,
													'allow_custom' => 0,
													'search_placeholder' => '',
													'allow_null' => 0,
													'ui' => 0,
													'ajax' => 0,
													'placeholder' => '',
													'parent_repeater' => 'field_forms_66604fa407aa6',
												),
											),
										),
									),
									'min' => '',
									'max' => '1',
									'acfe_flexible_render_template' => false,
									'acfe_flexible_render_style' => false,
									'acfe_flexible_render_script' => false,
									'acfe_flexible_thumbnail' => false,
									'acfe_flexible_settings' => false,
									'acfe_flexible_settings_size' => false,
									'acfe_flexible_modal_edit_size' => false,
									'acfe_flexible_category' => false,
								),
								'layout_forms_663df65851333' => array(
									'key' => 'layout_forms_663df65851333',
									'name' => 'contact',
									'label' => 'Контакт',
									'display' => 'block',
									'sub_fields' => array(
										array(
											'key' => 'field_forms_663df65851334',
											'label' => 'Заголовок',
											'name' => 'title',
											'aria-label' => '',
											'type' => 'text',
											'instructions' => '',
											'required' => 1,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => 'Новое сообщение с сайта',
											'maxlength' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
										),
										array(
											'key' => 'field_forms_66604dea581d5',
											'label' => 'Поля контакта',
											'name' => 'fields',
											'aria-label' => '',
											'type' => 'repeater',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'layout' => 'table',
											'min' => 0,
											'max' => 0,
											'collapsed' => 'field_forms_66604e04581d6',
											'button_label' => 'Добавить поле',
											'rows_per_page' => 20,
											'acfe_repeater_stylised_button' => 0,
											'sub_fields' => array(
												array(
													'key' => 'field_forms_66604e04581d6',
													'label' => 'Название поля',
													'name' => 'name',
													'aria-label' => '',
													'type' => 'select',
													'instructions' => '',
													'required' => 1,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '25',
														'class' => '',
														'id' => '',
													),
													'choices' => array(
														'EMAIL' => 'Email',
														'PHONE' => 'Телефон',
													),
													'default_value' => false,
													'return_format' => 'value',
													'multiple' => 0,
													'allow_custom' => 0,
													'search_placeholder' => '',
													'allow_null' => 0,
													'ui' => 1,
													'ajax' => 0,
													'placeholder' => '',
													'parent_repeater' => 'field_forms_66604dea581d5',
												),
												array(
													'key' => 'field_forms_66604e28581d7',
													'label' => 'Значение',
													'name' => 'value',
													'aria-label' => '',
													'type' => 'text',
													'instructions' => '',
													'required' => 1,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '25',
														'class' => '',
														'id' => '',
													),
													'default_value' => '',
													'maxlength' => '',
													'placeholder' => '',
													'prepend' => '',
													'append' => '',
													'parent_repeater' => 'field_forms_66604dea581d5',
												),
												array(
													'key' => 'field_6933268e6b0de',
													'label' => 'Тип',
													'name' => 'type',
													'aria-label' => '',
													'type' => 'select',
													'instructions' => '',
													'required' => 0,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '25',
														'class' => '',
														'id' => '',
													),
													'choices' => array(
														'text' => 'Текст',
														'number' => 'Число',
														'file' => 'Файл',
														'array' => 'Массив',
													),
													'default_value' => false,
													'return_format' => 'value',
													'multiple' => 0,
													'allow_custom' => 0,
													'search_placeholder' => '',
													'allow_null' => 0,
													'ui' => 0,
													'ajax' => 0,
													'placeholder' => '',
													'parent_repeater' => 'field_forms_66604dea581d5',
												),
											),
										),
									),
									'min' => '',
									'max' => '1',
									'acfe_flexible_render_template' => false,
									'acfe_flexible_render_style' => false,
									'acfe_flexible_render_script' => false,
									'acfe_flexible_thumbnail' => false,
									'acfe_flexible_settings' => false,
									'acfe_flexible_settings_size' => false,
									'acfe_flexible_modal_edit_size' => false,
									'acfe_flexible_category' => false,
								),
								'layout_forms_663df6075132f' => array(
									'key' => 'layout_forms_663df6075132f',
									'name' => 'comment',
									'label' => 'Комментарий',
									'display' => 'block',
									'sub_fields' => array(
										array(
											'key' => 'field_forms_663df60751330',
											'label' => 'Текст комментария',
											'name' => 'content',
											'aria-label' => '',
											'type' => 'textarea',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => 'Данные формы:
{{ __fields }}

Страница: {{ __page }}
Заголовок: {{ __title }}
Форма: {{ __form }}
Запрос: {{ __query }}
Браузер: {{ __browser }}
IP: {{ __ip }}',
											'maxlength' => '',
											'rows' => 10,
											'placeholder' => '',
											'new_lines' => '',
											'acfe_textarea_code' => 0,
										),
										array(
											'key' => 'field_6791b55728c07',
											'label' => 'Показывать строки с пустыми значениями полей',
											'name' => 'show_empty_fields',
											'aria-label' => '',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
											'ui' => 1,
										),
									),
									'min' => '',
									'max' => '1',
									'acfe_flexible_render_template' => false,
									'acfe_flexible_render_style' => false,
									'acfe_flexible_render_script' => false,
									'acfe_flexible_thumbnail' => false,
									'acfe_flexible_settings' => false,
									'acfe_flexible_settings_size' => false,
									'acfe_flexible_modal_edit_size' => false,
									'acfe_flexible_category' => false,
								),
							),
							'min' => '',
							'max' => '',
							'button_label' => 'Добавить сущность',
							'acfe_flexible_stylised_button' => false,
							'acfe_flexible_hide_empty_message' => false,
							'acfe_flexible_empty_message' => '',
							'acfe_flexible_layouts_templates' => false,
							'acfe_flexible_layouts_previews' => false,
							'acfe_flexible_layouts_placeholder' => false,
							'acfe_flexible_layouts_thumbnails' => false,
							'acfe_flexible_modal_settings' => array(
								'acfe_flexible_modal_settings_enabled' => false,
								'acfe_flexible_modal_settings_size' => 'large',
								'acfe_flexible_modal_settings_close' => true,
								'acfe_flexible_modal_settings_close_label' => '',
							),
							'acfe_flexible_async' => array(
							),
							'acfe_flexible_add_actions' => array(
							),
							'acfe_flexible_close_button_label' => '',
							'acfe_flexible_remove_button' => array(
							),
							'acfe_flexible_remove_top_actions' => array(
							),
							'acfe_flexible_layouts_state' => false,
							'acfe_flexible_modal_edit' => array(
								'acfe_flexible_modal_edit_enabled' => false,
								'acfe_flexible_modal_edit_size' => 'large',
							),
							'acfe_flexible_modal' => array(
								'acfe_flexible_modal_enabled' => false,
								'acfe_flexible_modal_title' => false,
								'acfe_flexible_modal_size' => 'xlarge',
								'acfe_flexible_modal_col' => '4',
								'acfe_flexible_modal_categories' => false,
							),
						),
					),
					'min' => '',
					'max' => '',
					'acfe_flexible_render_template' => false,
					'acfe_flexible_render_style' => false,
					'acfe_flexible_render_script' => false,
					'acfe_flexible_thumbnail' => false,
					'acfe_flexible_settings' => false,
					'acfe_flexible_settings_size' => false,
					'acfe_flexible_modal_edit_size' => false,
					'acfe_flexible_category' => false,
				),
				'layout_forms_663dce55a9372' => array(
					'key' => 'layout_forms_663dce55a9372',
					'name' => '__amo',
					'label' => 'Отправка в AMO',
					'display' => 'block',
					'sub_fields' => array(
						array(
							'key' => 'field_forms_663de5e6e32e5',
							'label' => 'Включить',
							'name' => 'enabled',
							'aria-label' => '',
							'type' => 'true_false',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '',
							'default_value' => 1,
							'ui_on_text' => '',
							'ui_off_text' => '',
							'ui' => 1,
						),
						array(
							'key' => 'field_forms_663de5d7e32e4',
							'label' => 'Отбор по селектору',
							'name' => 'selector',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => 'form',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_6669b14e560d2',
							'label' => 'Поддомен АМО',
							'name' => 'domen',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_6669b15f560d3',
							'label' => 'Секретный ключ',
							'name' => 'secret_key',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_6669b1ae560d4',
							'label' => 'ID интеграции',
							'name' => 'client_id',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_6669b1cf560d5',
							'label' => 'Код авторизации',
							'name' => 'auth_code',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_6672f3860fe5a',
							'label' => 'Редирект',
							'name' => 'redirect',
							'aria-label' => '',
							'type' => 'text',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '50',
								'class' => '',
								'id' => '',
							),
							'default_value' => '',
							'maxlength' => '',
							'placeholder' => '',
							'prepend' => '',
							'append' => '',
						),
						array(
							'key' => 'field_667e8769bcca2',
							'label' => 'Загрузить поля из АМО',
							'name' => '',
							'aria-label' => '',
							'type' => 'message',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'message' => '<a href="" class="AMOFields button button-primary button-large">Обновить поля</a>&nbsp;&nbsp;<a href="" class="AMOToken button button-primary button-large">Получить токен</a>',
							'new_lines' => '',
							'esc_html' => 0,
						),
						array(
							'key' => 'field_6669b38168a08',
							'label' => 'Создать в AMO',
							'name' => 'entities',
							'aria-label' => '',
							'type' => 'flexible_content',
							'instructions' => '',
							'required' => 0,
							'conditional_logic' => 0,
							'wrapper' => array(
								'width' => '',
								'class' => '',
								'id' => '',
							),
							'layouts' => array(
								'layout_667e6a2bc36fb' => array(
									'key' => 'layout_667e6a2bc36fb',
									'name' => 'contact',
									'label' => 'Контакт',
									'display' => 'block',
									'sub_fields' => array(
										array(
											'key' => 'field_667e6a2bc36fc',
											'label' => 'Заголовок',
											'name' => 'title',
											'aria-label' => '',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'maxlength' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
										),
										array(
											'key' => 'field_667e7d1f125ba',
											'label' => 'Произвольные поля',
											'name' => 'fields',
											'aria-label' => '',
											'type' => 'repeater',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'layout' => 'table',
											'min' => 0,
											'max' => 0,
											'collapsed' => 'field_667e7d3d125bb',
											'button_label' => 'Добавить',
											'rows_per_page' => 20,
											'acfe_repeater_stylised_button' => 0,
											'sub_fields' => array(
												array(
													'key' => 'field_667e7d3d125bb',
													'label' => 'Название поля',
													'name' => 'field',
													'aria-label' => '',
													'type' => 'select',
													'instructions' => '',
													'required' => 1,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '',
														'class' => '',
														'id' => '',
													),
													'choices' => array(
													),
													'default_value' => false,
													'return_format' => 'array',
													'multiple' => 0,
													'allow_null' => 0,
													'ui' => 0,
													'ajax' => 0,
													'placeholder' => '',
													'parent_repeater' => 'field_667e7d1f125ba',
													'allow_custom' => 0,
													'search_placeholder' => '',
													'create_options' => 0,
													'save_options' => 0,
												),
												array(
													'key' => 'field_667e7d4c125bc',
													'label' => 'Значение',
													'name' => 'value',
													'aria-label' => '',
													'type' => 'text',
													'instructions' => '',
													'required' => 1,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '',
														'class' => '',
														'id' => '',
													),
													'default_value' => '',
													'maxlength' => '',
													'placeholder' => '',
													'prepend' => '',
													'append' => '',
													'parent_repeater' => 'field_667e7d1f125ba',
												),
											),
										),
									),
									'min' => '',
									'max' => '1',
									'acfe_flexible_render_template' => false,
									'acfe_flexible_render_style' => false,
									'acfe_flexible_render_script' => false,
									'acfe_flexible_thumbnail' => false,
									'acfe_flexible_settings' => false,
									'acfe_flexible_settings_size' => false,
									'acfe_flexible_modal_edit_size' => false,
									'acfe_flexible_category' => false,
								),
								'layout_6669b5a4b2699' => array(
									'key' => 'layout_6669b5a4b2699',
									'name' => 'lead',
									'label' => 'Сделка',
									'display' => 'block',
									'sub_fields' => array(
										array(
											'key' => 'field_6669b5a4b269a',
											'label' => 'Заголовок',
											'name' => 'title',
											'aria-label' => '',
											'type' => 'text',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '66',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'maxlength' => '',
											'placeholder' => '',
											'prepend' => '',
											'append' => '',
										),
										array(
											'key' => 'field_668421a5e647b',
											'label' => 'Сумма',
											'name' => 'price',
											'aria-label' => '',
											'type' => 'number',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '33',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'min' => '',
											'max' => '',
											'placeholder' => '',
											'step' => '',
											'prepend' => '',
											'append' => '',
										),
										array(
											'key' => 'field_6678119b3166b',
											'label' => 'Воронка',
											'name' => 'pipeline_id',
											'aria-label' => '',
											'type' => 'select',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '33',
												'class' => '',
												'id' => '',
											),
											'choices' => array(
											),
											'default_value' => false,
											'return_format' => 'value',
											'multiple' => 0,
											'allow_null' => 1,
											'ui' => 1,
											'ajax' => 0,
											'placeholder' => '',
											'allow_custom' => 0,
											'search_placeholder' => '',
											'create_options' => 0,
											'save_options' => 0,
										),
										array(
											'key' => 'field_667811bf3166d',
											'label' => 'Статус сделки',
											'name' => 'status_id',
											'aria-label' => '',
											'type' => 'select',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '33',
												'class' => '',
												'id' => '',
											),
											'choices' => array(
											),
											'default_value' => false,
											'return_format' => 'value',
											'multiple' => 0,
											'allow_null' => 1,
											'ui' => 1,
											'ajax' => 0,
											'placeholder' => '',
											'allow_custom' => 0,
											'search_placeholder' => '',
											'create_options' => 0,
											'save_options' => 0,
										),
										array(
											'key' => 'field_667811563166a',
											'label' => 'Ответственный',
											'name' => 'responsible_user_id',
											'aria-label' => '',
											'type' => 'select',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '33',
												'class' => '',
												'id' => '',
											),
											'choices' => array(
											),
											'default_value' => false,
											'return_format' => 'value',
											'multiple' => 0,
											'allow_null' => 1,
											'ui' => 1,
											'ajax' => 0,
											'placeholder' => '',
											'allow_custom' => 0,
											'search_placeholder' => '',
											'create_options' => 0,
											'save_options' => 0,
										),
										array(
											'key' => 'field_6673b1db22869',
											'label' => 'Произвольные поля',
											'name' => 'fields',
											'aria-label' => '',
											'type' => 'repeater',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'layout' => 'table',
											'min' => 0,
											'max' => 0,
											'collapsed' => 'field_6673b1db2286a',
											'button_label' => 'Добавить поле',
											'rows_per_page' => 20,
											'acfe_repeater_stylised_button' => 0,
											'sub_fields' => array(
												array(
													'key' => 'field_6673b1db2286a',
													'label' => 'Название поля',
													'name' => 'field',
													'aria-label' => '',
													'type' => 'select',
													'instructions' => '',
													'required' => 1,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '33',
														'class' => '',
														'id' => '',
													),
													'choices' => array(
													),
													'default_value' => false,
													'return_format' => 'array',
													'multiple' => 0,
													'allow_null' => 0,
													'ui' => 1,
													'ajax' => 0,
													'placeholder' => '',
													'parent_repeater' => 'field_6673b1db22869',
													'allow_custom' => 0,
													'search_placeholder' => '',
													'create_options' => 0,
													'save_options' => 0,
												),
												array(
													'key' => 'field_6673b1db2286b',
													'label' => 'Значение',
													'name' => 'value',
													'aria-label' => '',
													'type' => 'text',
													'instructions' => '',
													'required' => 1,
													'conditional_logic' => 0,
													'wrapper' => array(
														'width' => '',
														'class' => '',
														'id' => '',
													),
													'default_value' => '',
													'maxlength' => '',
													'placeholder' => '',
													'prepend' => '',
													'append' => '',
													'parent_repeater' => 'field_6673b1db22869',
												),
											),
										),
									),
									'min' => '',
									'max' => '1',
									'acfe_flexible_render_template' => false,
									'acfe_flexible_render_style' => false,
									'acfe_flexible_render_script' => false,
									'acfe_flexible_thumbnail' => false,
									'acfe_flexible_settings' => false,
									'acfe_flexible_settings_size' => false,
									'acfe_flexible_modal_edit_size' => false,
									'acfe_flexible_category' => false,
								),
								'layout_6669b5c5b269b' => array(
									'key' => 'layout_6669b5c5b269b',
									'name' => 'note',
									'label' => 'Примечание',
									'display' => 'block',
									'sub_fields' => array(
										array(
											'key' => 'field_6669b5c5b269c',
											'label' => 'Текст комментария',
											'name' => 'content',
											'aria-label' => '',
											'type' => 'textarea',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'default_value' => '',
											'maxlength' => '',
											'rows' => '',
											'placeholder' => '',
											'new_lines' => '',
											'acfe_textarea_code' => 0,
										),
										array(
											'key' => 'field_6791b57528c08',
											'label' => 'Показывать строки с пустыми значениями полей',
											'name' => 'show_empty_fields',
											'aria-label' => '',
											'type' => 'true_false',
											'instructions' => '',
											'required' => 0,
											'conditional_logic' => 0,
											'wrapper' => array(
												'width' => '',
												'class' => '',
												'id' => '',
											),
											'message' => '',
											'default_value' => 0,
											'ui_on_text' => '',
											'ui_off_text' => '',
											'ui' => 1,
										),
									),
									'min' => '',
									'max' => '1',
									'acfe_flexible_render_template' => false,
									'acfe_flexible_render_style' => false,
									'acfe_flexible_render_script' => false,
									'acfe_flexible_thumbnail' => false,
									'acfe_flexible_settings' => false,
									'acfe_flexible_settings_size' => false,
									'acfe_flexible_modal_edit_size' => false,
									'acfe_flexible_category' => false,
								),
							),
							'min' => '',
							'max' => '',
							'button_label' => 'Добавить сущность',
							'acfe_flexible_advanced' => false,
							'acfe_flexible_stylised_button' => false,
							'acfe_flexible_hide_empty_message' => false,
							'acfe_flexible_empty_message' => '',
							'acfe_flexible_layouts_templates' => false,
							'acfe_flexible_layouts_previews' => false,
							'acfe_flexible_layouts_placeholder' => false,
							'acfe_flexible_layouts_thumbnails' => false,
							'acfe_flexible_modal_settings' => array(
								'acfe_flexible_modal_settings_enabled' => false,
								'acfe_flexible_modal_settings_size' => 'large',
								'acfe_flexible_modal_settings_close' => true,
								'acfe_flexible_modal_settings_close_label' => '',
							),
							'acfe_flexible_async' => array(
							),
							'acfe_flexible_add_actions' => array(
							),
							'acfe_flexible_close_button_label' => '',
							'acfe_flexible_remove_button' => array(
							),
							'acfe_flexible_remove_top_actions' => array(
							),
							'acfe_flexible_layouts_state' => false,
							'acfe_flexible_modal_edit' => array(
								'acfe_flexible_modal_edit_enabled' => false,
								'acfe_flexible_modal_edit_size' => 'large',
							),
							'acfe_flexible_modal' => array(
								'acfe_flexible_modal_enabled' => false,
								'acfe_flexible_modal_title' => false,
								'acfe_flexible_modal_size' => 'xlarge',
								'acfe_flexible_modal_col' => '4',
								'acfe_flexible_modal_categories' => false,
							),
						),
					),
					'min' => '',
					'max' => '',
					'acfe_flexible_render_template' => false,
					'acfe_flexible_render_style' => false,
					'acfe_flexible_render_script' => false,
					'acfe_flexible_thumbnail' => false,
					'acfe_flexible_settings' => false,
					'acfe_flexible_settings_size' => false,
					'acfe_flexible_modal_edit_size' => false,
					'acfe_flexible_category' => false,
				),
			),
			'min' => '',
			'max' => '',
			'button_label' => 'Добавить расширение',
			'acfe_flexible_stylised_button' => false,
			'acfe_flexible_hide_empty_message' => false,
			'acfe_flexible_empty_message' => '',
			'acfe_flexible_layouts_templates' => false,
			'acfe_flexible_layouts_previews' => false,
			'acfe_flexible_layouts_placeholder' => false,
			'acfe_flexible_layouts_thumbnails' => false,
			'acfe_flexible_modal_settings' => array(
				'acfe_flexible_modal_settings_enabled' => false,
				'acfe_flexible_modal_settings_size' => 'large',
				'acfe_flexible_modal_settings_close' => true,
				'acfe_flexible_modal_settings_close_label' => '',
			),
			'acfe_flexible_async' => array(
			),
			'acfe_flexible_add_actions' => array(
			),
			'acfe_flexible_close_button_label' => '',
			'acfe_flexible_remove_button' => array(
			),
			'acfe_flexible_remove_top_actions' => array(
			),
			'acfe_flexible_layouts_state' => false,
			'acfe_flexible_modal_edit' => array(
				'acfe_flexible_modal_edit_enabled' => false,
				'acfe_flexible_modal_edit_size' => 'large',
			),
			'acfe_flexible_modal' => array(
				'acfe_flexible_modal_enabled' => false,
				'acfe_flexible_modal_title' => false,
				'acfe_flexible_modal_size' => 'xlarge',
				'acfe_flexible_modal_col' => '4',
				'acfe_flexible_modal_categories' => false,
			),
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'options_page',
				'operator' => '==',
				'value' => 'wtw_forms',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'seamless',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
	'show_in_rest' => 0,
	'acfe_display_title' => '',
	'acfe_autosync' => '',
	'acfe_form' => 0,
	'acfe_meta' => '',
	'acfe_note' => '',
) );
} );

?><?php
?><?php

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('justvalidate', get_stylesheet_directory_uri() . '/js/just-validate.js', [], null, ['strategy' => 'defer']);
});
?>