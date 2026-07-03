<?php

error_reporting(E_ERROR | E_PARSE);

require_once dirname(dirname(dirname(__DIR__))) . '/wp-load.php';

$mail_settings = [];

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

if (function_exists('get_field')) {

  $default_profile = null;
  $mail_profiles = get_field('email_profile', 'option');

  if (empty($mail_profiles) && isset($_POST[__('Form', 'wtw-translate')])) {
    $result['delay'] = 0;
    $result['status'] = 'error';
    $result['error'] = 'Формы не настроены.';
    $result['error_msg'] = __('Sending error!', 'wtw-translate') . '<br><br>' . __('Error Description:', 'wtw-translate') . ' <b>' . $result['error'] . '</b>';
    die(json_encode($result));
  }

  if (is_array($mail_profiles)) {

    foreach ($mail_profiles as $profile) {
      if ($profile['form'] === 'default') {
        $default_profile = $profile;
      }
    }

    $field_name = __('Form', 'wtw-translate');
    $form = post($field_name);

    foreach ($mail_profiles as $profile) {
      $profile_forms = array_map('trim', explode(',', $profile['form']));
      if (in_array($form, $profile_forms)) {
        $form_profile = $profile;
      }
    }
  }

  if (!empty($form_profile)) {
    $mail_settings = $form_profile;
  } else {
    $mail_settings = $default_profile;
  }

  if (!empty($form_profile) && !empty($default_profile)) {
    if (!$form_profile['smtp'] && $default_profile['smtp']) {
      $mail_settings['smtp'] = true;
      $mail_settings['host'] = $default_profile['host'];
      $mail_settings['auth'] = $default_profile['auth'];
      $mail_settings['secure'] = $default_profile['secure'];
      $mail_settings['port'] = $default_profile['port'];
      $mail_settings['charset'] = $default_profile['charset'];
      $mail_settings['username'] = $default_profile['username'];
      $mail_settings['password'] = $default_profile['password'];
    }
  }
} else {
  die();
}

if (!isset($_POST[__('Form', 'wtw-translate')])) {
  print_js($mail_settings['recaptcha'], $mail_settings['recaptcha_site_key']);
} else {

  if (isset($_POST['g-recaptcha-response']) && empty($_POST['g-recaptcha-response'])) {
    die('ERROR_RECAPTCHA');
  }

  $result = [];
  $result['redirect'] = $mail_settings['redirect'];
  $result['redirect_new_tab'] = $mail_settings['redirect_new_tab'];
  $result['delay'] = $mail_settings['delay'];
  $result['hide'] = $mail_settings['hide'];
  $result['hide_lbox'] = $mail_settings['hide_lbox'];
  $result['lbox_class'] = $mail_settings['lbox_class'];
  $result['success_msg'] = $mail_settings['success_msg'];
  $result['error_msg'] = $mail_settings['error_msg'];

  $required_fields = [];

  if (isset($mail_settings['required_fields'])) {
    $required_fields = explode(';', $mail_settings['required_fields']);

    $required_fields = array_map('trim', $required_fields);
    $required_fields = array_map('strtolower', $required_fields);
  }

  $recaptcha_secret_key = $mail_settings['recaptcha_secret_key'];

  do_action('mailer_before_parse_fields', $_POST);

  $mail_settings = apply_filters('wtw_mail_settings_modify', $mail_settings, $_POST);

  $fields = "";
  foreach ($_POST as $key => $value) {
    if ($value === 'on') {
      $value = __('Yes', 'wtw-translate');
    }
    if ($key === 'sendto') {
      $email = $value;
    }
    if ($key === 'g-recaptcha-response' && $mail_settings['recaptcha']) {
      $recaptcha = $value;
      if (!empty($recaptcha)) {
        $google_url = "https://www.google.com/recaptcha/api/siteverify";
        $url = $google_url . "?secret=" . $recaptcha_secret_key . "&response=" . $recaptcha . "&remoteip=" . $_SERVER['REMOTE_ADDR'];
        $res = SiteVerify($url);
        $res = json_decode($res, true);
        if (!$res['success']) {
          echo 'ERROR_RECAPTCHA';
          die();
        }
      } else {
        echo 'ERROR_RECAPTCHA';
        die();
      }
    } elseif ($key === 'required_fields') {
      $required = explode(',', $value);
    } else {
      if (in_array(strtolower($key), $required_fields) && $value === '') {
        $result['delay'] = 0;
        $result['status'] = 'error';
        $result['error'] = 'ERROR_REQUIRED';
        $result['error_msg'] = __('Error Description:', 'wtw-translate');
        echo json_encode($result);
        die();
      }
      if (is_array($value)) {
        $fields .= str_replace('_', ' ', $key) . ': <b>' . implode(', ', $value) . '</b> <br />';
      } else {
        if ($value !== '') {
          $fields .= str_replace('_', ' ', $key) . ': <b>' . $value . '</b> <br />';
        }
      }
    }
  }
  // $fields .= 'IP: <b>' . $_SERVER['REMOTE_ADDR'] . '</b><br />';
  // $fields .= 'Browser: <b>' . $_SERVER['HTTP_USER_AGENT'] . '</b><br />';

  $mail_settings['subject'] = str_replace('%site%', $_SERVER['HTTP_HOST'], $mail_settings['subject']);
  $mail_settings['subject'] = str_replace('%ip%', $_SERVER['REMOTE_ADDR'], $mail_settings['subject']);

  $mail_settings['message'] = str_replace('%fields%', $fields, $mail_settings['message']);
  $mail_settings['message'] = str_replace('%ip%', $_SERVER['REMOTE_ADDR'], $mail_settings['message']);
  $mail_settings['message'] = str_replace('%browser%', $_SERVER['HTTP_USER_AGENT'], $mail_settings['message']);
  $mail_settings['message'] = replaceMessageFields($mail_settings['message']);

  do_action('mailer_before_send_mail', $mail_settings['email'], $mail_settings['subject'], $mail_settings['message'], $result, $_POST);

  wtw_send_mail($mail_settings['email'], $mail_settings['subject'], $mail_settings['message']);

  if ($mail_settings['reply'] && !empty($mail_settings['reply_email']) && isset($_POST[$mail_settings['reply_email']])) {
    $mail_settings['reply_subject'] = str_replace('%site%', $_SERVER['HTTP_HOST'], $mail_settings['reply_subject']);
    $mail_settings['reply_message'] = str_replace('%fields%', $fields, $mail_settings['reply_message']);
    $mail_settings['reply_message'] = replaceMessageFields($mail_settings['reply_message']);
    wtw_send_mail($_POST[$mail_settings['reply_email']], $mail_settings['reply_subject'], $mail_settings['reply_message'], true);
  }

  if ($mail_settings['export'] && $mail_settings['export_file'] !== '') {
    $vars = explode(',', $mail_settings['export_fields']);
    $str_arr[] = '"' . date("d.m.y H:i:s") . '"';
    foreach ($vars as $var_name) {
      if (isset($_POST[$var_name])) {
        $str_arr[] = '"' . $_POST[$var_name] . '"';
      }
      if (strtoupper($var_name) === '__FILES__') {

        $files_links = [];
        $files_array = reArrayFiles($_FILES['file']);

        if ($files_array !== false) {
          foreach ($files_array as $file) {
            if ($file['error'] === UPLOAD_ERR_OK) {

              $upload_path = 'uploads/forms_uploads';
              $upload_dir = WP_CONTENT_DIR . '/' . $upload_path;

              if (!is_dir($upload_dir)) {
                mkdir($upload_dir);
              }

              $file_name = time() . '_' . $file['name'];

              move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $file_name);

              $files_links[] = content_url($upload_path . '/' . $file_name);
            }
          }

          $str_arr[] = '"' . implode(' ', $files_links) . '"';
        }
      }
    }
    file_put_contents($mail_settings['export_file'], implode(';', $str_arr) . "\n", FILE_APPEND | LOCK_EX);
  }
}

function wtw_send_mail($to, $subject, $content, $reply_mode = false)
{
  if (!class_exists('PHPMailer')) {
    require_once __DIR__ . '/class-phpmailer.php';
  }

  global $mail_settings, $result;

  do_action('mailer_before_send', $mail_settings, $result);

  $mail = new PHPMailer(true);
  if ($mail_settings['smtp']) {
    $mail->IsSMTP();
  }
  try {
    $mail->SMTPDebug = 0;
    $mail->Host = $mail_settings['host'];
    $mail->SMTPAuth = $mail_settings['auth'];
    $mail->SMTPSecure = $mail_settings['secure'];
    $mail->Port = $mail_settings['port'];
    $mail->CharSet = $mail_settings['charset'];
    $mail->Username = $mail_settings['username'];
    $mail->Password = $mail_settings['password'];

    if (!empty($mail_settings['username'])) {
      $mail->SetFrom($mail_settings['username'], $mail_settings['from']);
    } elseif (!empty($mail_settings['addreply'])) {
      $mail->SetFrom($mail_settings['addreply'], $mail_settings['from']);
    }

    if (!empty($mail_settings['addreply'])) {
      $mail->AddReplyTo($mail_settings['addreply'], $mail_settings['from']);
    }

    $to_array = explode(',', $to);
    foreach ($to_array as $to) {
      $mail->AddAddress($to);
    }
    if (!empty($mail_settings['cc'])) {
      $to_array = explode(',', $mail_settings['cc']);
      foreach ($to_array as $to) {
        $mail->AddCC($to);
      }
    }
    if (!empty($mail_settings['bcc'])) {
      $to_array = explode(',', $mail_settings['bcc']);
      foreach ($to_array as $to) {
        $mail->AddBCC($to);
      }
    }

    $mail->Subject = htmlspecialchars($subject);
    $mail->MsgHTML($content);

    if (!empty($mail_settings['reply_file']) && $reply_mode) {
      $mail->AddAttachment(str_replace(get_site_url(), ABSPATH, $mail_settings['reply_file']['url']));
    } elseif (!$reply_mode) {
      $files_array = reArrayFiles($_FILES['file']);
      if ($files_array !== false) {
        foreach ($files_array as $file) {
          if ($file['error'] === UPLOAD_ERR_OK) {
            $mail->AddAttachment($file['tmp_name'], $file['name']);
          }
        }
      }
    }

    $mail->Send();

    do_action('mailer_after_send', $result);

    if (!$reply_mode) {
      $result['status'] = 'success';
      echo json_encode($result);
    };
  } catch (phpmailerException $e) {
    $result['delay'] = 0;
    $result['status'] = 'error';
    $result['error'] = strip_tags($e->errorMessage());
    $result['error_msg'] = $mail_settings['error_msg'] . '<br><br>' . __('Error Description:', 'wtw-translate') . ' ' . strip_tags($result['error']);
    echo json_encode($result);
  } catch (Exception $e) {
    $result['delay'] = 0;
    $result['status'] = 'error';
    $result['error'] = strip_tags($e->getMessage());
    $result['error_msg'] = $mail_settings['error_msg'] . '<br><br>' . __('Error Description:', 'wtw-translate') . ' ' . strip_tags($result['error']);
    echo json_encode($result);
  }
}

function SiteVerify($url)
{
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl, CURLOPT_TIMEOUT, 15);
  curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36");
  $curlData = curl_exec($curl);
  curl_close($curl);
  return $curlData;
}

function post($key)
{
  if (isset($_POST[$key])) {
    return $_POST[$key];
  } else {
    return '';
  }
}

function get($key)
{
  if (isset($_GET[$key])) {
    return $_POST[$key];
  } else {
    return '';
  }
}

function replaceMessageFields($string)
{
  foreach ($_POST as $key => $value) {
    $search = '%' . $key . '%';
    $string = str_replace($search, $value, $string);
  }
  return $string;
}


function print_js($recaptcha, $recaptcha_site_key)
{
  if ($recaptcha) { ?>
    <script src='https://www.google.com/recaptcha/api.js'></script><?php } ?>
  <script id="mailer" type="text/javascript">
    var $ = jQuery.noConflict();
    var form_ids = [],
      cur_id = '';
    $('.w-form form:not([action],[data-action]),[action=""]').each(function() {
      $(this).attr('action', '/').attr('method', 'post');
      cur_id = $(this).attr('id');
      if (cur_id === undefined) {
        cur_id = 'form_id_' + form_ids.length;
        $(this).attr('id', cur_id);
      } else if (form_ids.indexOf(cur_id) !== -1) {
        cur_id = cur_id + form_ids.length;
        $(this).attr('id', cur_id);
      }
      form_ids.push(cur_id);
      $(this).find('.g-recaptcha').attr('data-sitekey',
        '<?= $recaptcha_site_key; ?>');
    });

    $('.w-form form[data-name],.w-form input[data-name],.w-form select[data-name],.w-form textarea[data-name]').each(
      function(indx) {
        $(this).attr('name', $(this).attr('data-name'));
      });
    $('textarea').focus(function() {
      if ($(this).val().trim() === '') $(this).val('');
    });
    $('textarea').each(function() {
      if ($(this).val().trim() === '') $(this).val('');
    });

    jQuery(document).ready(function($) {

      $('.w-form form[action = "/"]').submit(function(e) {

        e.preventDefault();

        $(this).find('[type=submit]').disable = true;

        action =
          '<?php bloginfo('template_url'); ?>/mailer.php';
        cur_id = '#' + $(this).attr('id');

        $(cur_id).parent().find('.w-form-done,.w-form-fail').hide();

        cur_action = $(cur_id).attr('action');
        if (cur_action !== '/') {
          action = cur_action;
        }

        submit_input = $(cur_id).find('[type = submit]');
        submit_label = submit_input.val();
        if (submit_input.attr('data-wait') === 'Please wait...') {
          submit_input.val('<?php echo __('Sending...', 'wtw-translate') ?>');
        } else {
          submit_input.val(submit_input.attr('data-wait'));
        }

        if ($(cur_id + ' [name=<?php echo __('Form', 'wtw-translate') ?>]').is('input')) {
          $(cur_id).find('[name=<?php echo __('Form', 'wtw-translate') ?>]').val($(cur_id).attr('data-name'));
        } else {
          $('<input type="hidden" data-name="<?php echo __('Form', 'wtw-translate') ?>" name="<?php echo __('Form', 'wtw-translate') ?>" value="' + $(cur_id).attr('data-name') + '">')
            .prependTo(cur_id);
        }

        if ($(cur_id + ' [name=<?php echo __('Query', 'wtw-translate') ?>]').is('input')) {
          $(cur_id).find('[name=<?php echo __('Query', 'wtw-translate') ?>]').val(document.location.search);
        } else {
          $('<input type="hidden" data-name="<?php echo __('Query', 'wtw-translate') ?>" name="<?php echo __('Query', 'wtw-translate') ?>" value="' + document.location.search + '">')
            .prependTo(cur_id);
        }

        if ($(cur_id + ' [name=<?php echo __('Title', 'wtw-translate') ?>]').is('input')) {
          $(cur_id).find('[name=<?php echo __('Title', 'wtw-translate') ?>]').val(document.title);
        } else {
          $('<input type="hidden" data-name="<?php echo __('Title', 'wtw-translate') ?>" name="<?php echo __('Title', 'wtw-translate') ?>" value="' + document.title + '">')
            .prependTo(cur_id);
        }

        if ($(cur_id + ' [name=<?php echo __('Page', 'wtw-translate') ?>]').is('input')) {
          $(cur_id).find('[name=<?php echo __('Page', 'wtw-translate') ?>]').val(document.location.origin + document.location.pathname);
        } else {
          $('<input type="hidden" data-name="<?php echo __('Page', 'wtw-translate') ?>" name="<?php echo __('Page', 'wtw-translate') ?>" value="' + document.location.origin +
            document.location.pathname + '">').prependTo(cur_id);
        }

        $('<input type="hidden" name="required_fields">').prependTo(cur_id);
        required_fields = '';

        required_fields = '';
        $(cur_id).find('[required=required]').each(function() {
          required_fields = required_fields + ',' + $(this).attr('name');
        });
        if (required_fields !== '') {
          $(cur_id).find('[name=required_fields]').val(required_fields);
        }

        var formData = new FormData($(cur_id)[0]);
        $.ajax({
            url: action,
            type: 'POST',
            processData: false,
            contentType: false,
            data: formData
          })
          .done(function(result) {

            if (typeof(wtw_after_send_from) === 'function') {
              wtw_after_send_from(result);
            }

            $(this).find('[type=submit]').disable = false;

            if (result === 'ERROR_RECAPTCHA') {
              alert('<?php echo __('Confirm that you are not a robot!', 'wtw-translate') ?>');
              submit_input.val(submit_label);
              return;
            }

            if (!isJson(result)) {
              console.log(result);
              alert('<?php echo __('Sending error!', 'wtw-translate') ?>');
              return;
            }

            result = JSON.parse(result);

            if (result['success_msg'] != '') {
              $(cur_id).parent().find('.w-form-done').html('<div>' + result['success_msg'] + '</div>');
            }

            $(cur_id).parent().find('.w-form-fail').html('<div>' + result['error_msg'] + '</div>');

            submit_input.val(submit_label);

            if (result['status'] == 'success') {
              if ($(cur_id).attr('data-redirect') !== undefined) {
                document.location.href = $(cur_id).attr('data-redirect');
              } else if (result['redirect'] !== '' && result['redirect'] !== '/-') {
                if (result['redirect_new_tab']) {
                  window.open(result['redirect']);
                } else {
                  document.location.href = result['redirect'];
                }
                return (true);
              }
              $(cur_id).siblings('.w-form-fail').hide();
              replay_class = '.w-form-done';
              replay_msg = result['success_msg'];
            } else {
              $(cur_id).siblings('.w-form-done').hide();
              if (result['error'] === 'ERROR_REQUIRED') {
                replay_msg = '<?php echo __('Required field not filled!', 'wtw-translate') ?>'
              } else {
                replay_msg = result['error_msg'];
              }
              replay_class = '.w-form-fail';
            }

            replay_div = $(cur_id).siblings(replay_class);
            replay_div.show();
            if (result['hide']) {
              $(cur_id).hide();
            }

            result['delay'] = parseInt(result['delay']);
            if (result['delay'] !== 0) {
              if (result['hide_lbox'] && result['status'] == 'success') {
                setTimeout(function() {
                  $('.' + result['lbox_class'].replace(/,/g, ",.").replace(/ /g, "")).fadeOut();
                  $('body').css('overflow', 'visible');
                }, result['delay']);
              }
              setTimeout(function() {
                replay_div.fadeOut();
                $(cur_id).fadeIn();
              }, result['delay'] + 1000);
            }

            if (result['status'] == 'success') {
              $(cur_id).trigger("reset");
              $(cur_id).find('div[for]').hide();
            }

            submit_input.val(submit_label);
          });
      });

      $('label[for^=file]').each(function() {
        file_id = $(this).attr('for');
        $(this).after('<input name="file[]" type="file" id="' + file_id + '" multiple style="display:none;">');
        $(this).siblings('div[for]').hide();
        $('input#' + file_id).change(function() {
          file_name = $(this).val().replace('C:\\fakepath\\', "");
          file_text = $(this).siblings('div[for]').text().replace('%file%', file_name);
          if (file_text.trim() === '') file_text = '<?php echo __('File attached', 'wtw-translate') ?>';
          $(this).siblings('div[for]').text(file_text).show();
        });
      });
    });

    function isJson(str) {
      try {
        JSON.parse(str);
      } catch (e) {
        return false;
      }
      return true;
    }
  </script>
<?php
}
