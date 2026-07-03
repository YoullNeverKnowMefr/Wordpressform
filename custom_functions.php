<?php

// =========================================================================
// 1. ПОДКЛЮЧЕНИЕ СКРИПТОВ, СТИЛЕЙ И КУКИ
// =========================================================================

add_action('wp_enqueue_scripts', 'wtw_custom_code');

function wtw_custom_code() {
    // SimpleBar
    $simplebar_css_ver = filemtime(get_stylesheet_directory() . '/css/simplebar.min.css');
    $simplebar_js_ver  = filemtime(get_stylesheet_directory() . '/js/simplebar.min.js');
    wp_enqueue_style('simplebar-css', get_stylesheet_directory_uri() . '/css/simplebar.min.css', array(), $simplebar_css_ver);
    wp_enqueue_script('simplebar-js', get_stylesheet_directory_uri() . '/js/simplebar.min.js', array(), $simplebar_js_ver, true);

    // Selectize
    $selectize_css_ver = filemtime(get_stylesheet_directory() . '/css/selectize.min.css');
    $selectize_js_ver  = filemtime(get_stylesheet_directory() . '/js/selectize.min.js');
    wp_enqueue_style('selectize-css', get_stylesheet_directory_uri() . '/css/selectize.min.css', array(), $selectize_css_ver);
    wp_enqueue_script('selectize-js', get_stylesheet_directory_uri() . '/js/selectize.min.js', array('jquery'), $selectize_js_ver, true);

    // Intl-tel-input
    $iti_css_ver = filemtime(get_stylesheet_directory() . '/css/intlTelInput.min.css');
    $iti_js_ver  = filemtime(get_stylesheet_directory() . '/js/intlTelInput.min.js');
    wp_enqueue_style('intl-tel-input-css', get_stylesheet_directory_uri() . '/css/intlTelInput.min.css', array(), $iti_css_ver);
    wp_enqueue_script('intl-tel-input-js', get_stylesheet_directory_uri() . '/js/intlTelInput.min.js', array(), $iti_js_ver, true);
  
    // Кастомные файлы
    $css_version = filemtime(get_stylesheet_directory() . '/css/custom.css');
    $js_version  = filemtime(get_stylesheet_directory() . '/js/custom.js');
    wp_enqueue_style('custom-css', get_stylesheet_directory_uri() . '/css/custom.css', array('main', 'simplebar-css', 'intl-tel-input-css'), $css_version);
    wp_enqueue_script('custom-js', get_stylesheet_directory_uri() . '/js/custom.js', array('jquery', 'simplebar-js', 'intl-tel-input-js'), $js_version, true);
}

add_action('wp_footer', 'add_cookie_logic_to_footer');

function add_cookie_logic_to_footer() {
    if ( is_admin() ) {
        return;
    }
    
    get_template_part('template-parts/cookies');
    ?>
    <script>
        function setCookie(name, value, days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + "=" + value + ";expires=" + date.toUTCString() + ";path=/";
        }
        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for(let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }
        document.addEventListener("DOMContentLoaded", function () {
            const modal = document.getElementById("cookieModal");
            const acceptBtn = document.getElementById("acceptCookies");

            if (!getCookie("cookiesAccepted") && modal) {
                modal.style.display = "block";
            }
            if (acceptBtn) {
                acceptBtn.addEventListener("click", function (e) {
                    e.preventDefault();
                    setCookie("cookiesAccepted", "true", 30);
                    modal.style.display = "none";
                });
            }
        });
    </script>
    <?php
}

// =========================================================================
// 2. ФУНКЦИИ ЦЕНООБРАЗОВАНИЯ И ДАТ ДЛЯ ШАБЛОНА ВАМ
// =========================================================================

function get_finalnaya_cena() {
    $cena_kursa = (int) get_sub_field('cena_kursa'); 
    if (!$cena_kursa) return 0;
    
    $current_date = wp_date('Ymd');
    $periody = get_sub_field('periody_cen'); 

    if( !empty($periody) && is_array($periody) ) {
        foreach( $periody as $period ) {
            if( $period['data_perioda'] >= $current_date ) {
                return (int) $period['cena_perioda']; 
            }
        }
    }
    return $cena_kursa; 
}

function get_skidka_percent() {
    $cena_kursa = (int) get_sub_field('cena_kursa');
    $finalnaya_cena = get_finalnaya_cena();
    
    if ($cena_kursa > 0 && $finalnaya_cena < $cena_kursa) {
        return round(100 - (($finalnaya_cena / $cena_kursa) * 100));
    }
    return 0;
}

function get_tekst_dedlajna() {
    $current_date = wp_date('Ymd');
    $periody = get_sub_field('periody_cen');

    if( !empty($periody) && is_array($periody) ) {
        foreach( $periody as $period ) {
            if( $period['data_perioda'] >= $current_date ) {
                return wp_date('j F', strtotime($period['data_perioda']));
            }
        }
    }
    return ''; 
}

function get_data_potoka_text() {
    $start_date = get_sub_field('data_nachala_potoka'); 
    $end_date = get_sub_field('data_okonchaniya_potoka');     
    if (!$start_date) return 'Новый поток';

    $months = [
        '01' => 'ЯНВАРЯ', '02' => 'ФЕВРАЛЯ', '03' => 'МАРТА', '04' => 'АПРЕЛЯ', 
        '05' => 'МАЯ', '06' => 'ИЮНЯ', '07' => 'ИЮЛЯ', '08' => 'АВГУСТА', 
        '09' => 'СЕНТЯБРЯ', '10' => 'ОКТЯБРЯ', '11' => 'НОЯБРЯ', '12' => 'ДЕКАБРЯ'
    ];

    $start_day = (int) substr($start_date, 6, 2);
    $start_month = substr($start_date, 4, 2);

    if (!$end_date || $start_date === $end_date) {
        return $start_day . ' ' . $months[$start_month];
    }

    $end_day = (int) substr($end_date, 6, 2);
    $end_month = substr($end_date, 4, 2);

    if ($start_month === $end_month) {
        return $start_day . '-' . $end_day . ' ' . $months[$start_month];
    } else {
        return $start_day . ' ' . $months[$start_month] . ' - ' . $end_day . ' ' . $months[$end_month];
    }
}

function get_dney_do_povysheniya() {
    $bronirovanie = get_field('bronirovanie', get_the_ID());
    if ( empty($bronirovanie) || empty($bronirovanie['potoki_kursa']) ) return '';
    
    $pervyj_potok = $bronirovanie['potoki_kursa'][0];
    $periody = $pervyj_potok['periody_cen'];
    
    if ( empty($periody) || !is_array($periody) ) return '';
    
    $dates = array();
    foreach ( $periody as $period ) {
        if (!empty($period['data_perioda'])) {
            $dates[] = $period['data_perioda']; 
        }
    }
    
    if(empty($dates)) return '';
    $dates_json = htmlspecialchars(json_encode($dates), ENT_QUOTES, 'UTF-8');
    
    return '<span class="js-dynamic-deadline" data-deadlines="' . $dates_json . '"></span>';
}

function is_povyshenie_ceny_active() {
    $bronirovanie = get_field('bronirovanie', get_the_ID());
    if ( empty($bronirovanie) || empty($bronirovanie['potoki_kursa']) ) return false;
    
    $pervyj_potok = $bronirovanie['potoki_kursa'][0];
    return !empty($pervyj_potok['periody_cen']);
}

// =========================================================================
// 3. ФУНКЦИИ ДЛЯ ХЕДЕРА, ВЫВОДА ГОРОДОВ И ДАТ
// =========================================================================

function get_short_date_potoka() {
    $start_date = get_sub_field('data_nachala_potoka'); 
    $end_date = get_sub_field('data_okonchaniya_potoka');     
    if (!$start_date) return '';

    $start_day = substr($start_date, 6, 2);
    $start_month = substr($start_date, 4, 2);

    if (!$end_date || $start_date === $end_date) {
        return $start_day . '.' . $start_month;
    }

    $end_day = substr($end_date, 6, 2);
    $end_month = substr($end_date, 4, 2);

    if ($start_month === $end_month) {
        return $start_day . '-' . $end_day . '.' . $start_month;
    } else {
        return $start_day . '.' . $start_month . ' - ' . $end_day . '.' . $end_month;
    }
}

function sy_get_first_stream_info() {
    $bronirovanie = get_field('bronirovanie', get_the_ID());
    $result = array( 'count' => 0, 'short_date' => '', 'full_date' => '' );
    
    if ( empty($bronirovanie) || empty($bronirovanie['potoki_kursa']) ) {
        return $result;
    }
    
    $potoki = $bronirovanie['potoki_kursa'];
    $result['count'] = count($potoki);
    
    $first = $potoki[0];
    $s_date = $first['data_nachala_potoka'];
    $e_date = $first['data_okonchaniya_potoka'];
    
    if (!$s_date) return $result;
    
    $s_day = substr($s_date, 6, 2); $s_month = substr($s_date, 4, 2);
    if (!$e_date || $s_date === $e_date) {
        $result['short_date'] = $s_day . '.' . $s_month;
    } else {
        $e_day = substr($e_date, 6, 2); $e_month = substr($e_date, 4, 2);
        if ($s_month === $e_month) {
            $result['short_date'] = $s_day . '-' . $e_day . '.' . $s_month;
        } else {
            $result['short_date'] = $s_day . '.' . $s_month . ' - ' . $e_day . '.' . $e_month;
        }
    }
    
    $months = ['01'=>'января', '02'=>'февраля', '03'=>'марта', '04'=>'апреля', '05'=>'мая', '06'=>'июня', '07'=>'июля', '08'=>'августа', '09'=>'сентября', '10'=>'октября', '11'=>'ноября', '12'=>'декабря'];
    $year = substr($s_date, 0, 4);
    
    if (!$e_date || $s_date === $e_date) {
        $result['full_date'] = $s_day . ' ' . $months[$s_month] . ' ' . $year;
    } else {
        $e_day = substr($e_date, 6, 2); $e_month = substr($e_date, 4, 2);
        if ($s_month === $e_month) {
            $result['full_date'] = $s_day . '–' . $e_day . ' ' . $months[$s_month] . ' ' . $year;
        } else {
            $result['full_date'] = $s_day . ' ' . $months[$s_month] . ' – ' . $e_day . ' ' . $months[$e_month] . ' ' . $year;
        }
    }
    
    return $result;
}

function sy_get_gorod_v_padezhe($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    $manual_padezh = get_field('gorod_v_padezhe', $post_id);
    if ( !empty($manual_padezh) ) return $manual_padezh;

    $city_name = get_field('gorod_meropriyatiya', $post_id);
    if ( empty($city_name) ) return ''; 

    $city_name = trim($city_name);
    $transient_key = 'sy_city_pad_' . md5($city_name);
    $cached_city = get_transient($transient_key);

    if ( false !== $cached_city ) return $cached_city;

    $response = wp_remote_get( 'https://ws3.morpher.ru/russian/declension?format=json&s=' . urlencode($city_name) );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
        return 'в г. ' . $city_name;
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( isset($data['П']) ) {
        $declined_city = $data['П'];
        $first_letters = mb_strtolower(mb_substr($declined_city, 0, 2, 'UTF-8'), 'UTF-8');
        $predlog = ( in_array($first_letters, ['вл', 'вс', 'вт', 'вн', 'пт', 'пс', 'фл', 'фс']) ) ? 'во' : 'в';
        
        $result = $predlog . ' ' . $declined_city;
        set_transient($transient_key, $result, 30 * DAY_IN_SECONDS);
        return $result;
    }

    return 'в г. ' . $city_name;
}

function sy_get_gorod_v_padezhe_ucfirst() {
    $str = sy_get_gorod_v_padezhe();
    $first_char = mb_substr($str, 0, 1, 'UTF-8');
    $rest = mb_substr($str, 1, null, 'UTF-8');
    return mb_strtoupper($first_char, 'UTF-8') . $rest;
}

function sy_get_gorod_v_padezhe_proper() {
    $str = sy_get_gorod_v_padezhe(); 
    $words = explode(' ', mb_strtolower(trim($str), 'UTF-8'));
    
    foreach ($words as $key => $word) {
        if (in_array($word, ['в', 'во', 'г.'])) continue;
        $first_char = mb_substr($word, 0, 1, 'UTF-8');
        $rest = mb_substr($word, 1, null, 'UTF-8');
        $words[$key] = mb_strtoupper($first_char, 'UTF-8') . $rest;
    }
    return implode(' ', $words);
}

function sy_get_meditation_date() {
    $start_date = get_sub_field('data_nachala_potoka');
    if (!$start_date) return '';

    $months = [
        '01' => 'января', '02' => 'февраля', '03' => 'марта', '04' => 'апреля',
        '05' => 'мая', '06' => 'июня', '07' => 'июля', '08' => 'августа',
        '09' => 'сентября', '10' => 'октября', '11' => 'ноября', '12' => 'декабря'
    ];

    $year = substr($start_date, 0, 4);
    $month = substr($start_date, 4, 2);
    $day = (int)substr($start_date, 6, 2);

    return $day . ' ' . $months[$month] . ' ' . $year;
}

// =========================================================
// 4. ИНТЕГРАЦИЯ С ПРОДАМУСОМ (ГЕНЕРАЦИЯ ССЫЛКИ)
// =========================================================

add_action('wp_ajax_get_prodamus_link', 'soundyoga_get_prodamus_link');
add_action('wp_ajax_nopriv_get_prodamus_link', 'soundyoga_get_prodamus_link');

// Вспомогательная функция (Вынесена наружу, чтобы не ломался PHP)
function sy_prodamus_sort(&$array) {
    ksort($array);
    foreach ($array as &$value) {
        if (is_array($value)) {
            sy_prodamus_sort($value);
        }
    }
}

// Главная функция
function soundyoga_get_prodamus_link() {
    $secret_key = '27f82800eafd2e3d1b5b790e2c742cc7e7956da9911c9bc1f1e38957fc9fc0fc';
    $domain = 'https://soundyoga-pay.payform.ru/';

    $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
    
    // Телефон в строгом международном формате для Продамус
    $raw_phone = isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '';
    $raw_phone = preg_replace('/[^\d]/', '', $raw_phone);
    if (strlen($raw_phone) === 10 && strpos($raw_phone, '9') === 0) {
        $customer_phone = '+7' . $raw_phone;
    } elseif (strlen($raw_phone) === 11 && strpos($raw_phone, '8') === 0) {
        $customer_phone = '+7' . substr($raw_phone, 1);
    } elseif (strlen($raw_phone) >= 11) {
        $customer_phone = '+' . $raw_phone;
    } else {
        $customer_phone = $raw_phone ? '+' . $raw_phone : '';
    }

    $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
    $item_name = isset($_POST['item_name']) ? sanitize_text_field($_POST['item_name']) : 'Курс';
    $item_price = isset($_POST['item_price']) ? intval($_POST['item_price']) : 0;
    
    // ПРОВЕРКА НА ТИП ОПЛАТЫ (Защита от кэша JS на фронтенде)
    if (isset($_POST['all_fields']) && is_array($_POST['all_fields'])) {
        foreach ($_POST['all_fields'] as $field) {
            if ($field['name'] === 'Тип_оплаты' && $field['value'] === 'repass') {
                $item_price = 18000;
                // Если JS прислал старое название (Оплата 100%), заменяем его
                $item_name = str_replace(' (Оплата 100%)', '', $item_name);
                $item_name = str_replace(' (Предоплата 50%)', '', $item_name);
                if (strpos($item_name, '(Повторное прохождение)') === false) {
                    $item_name .= ' (Повторное прохождение)';
                }
                break;
            }
        }
    }

    
    // Получаем ID лендинга
    $post_id = isset($_POST['landing_id']) ? intval($_POST['landing_id']) : 0;
    
    // Добавляем город в название позиции для Продамуса
    if ($post_id > 0) {
        $city_padezh = sy_get_gorod_v_padezhe($post_id);
        if (!empty($city_padezh)) {
            $city_padezh = trim($city_padezh);
            // Защита от опечаток: если после "в " идет маленькая буква, делаем ее большой
            if (preg_match('/^в\s+(.+)$/iu', $city_padezh, $matches)) {
                $city_word = $matches[1];
                $city_word = mb_strtoupper(mb_substr($city_word, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($city_word, 1, null, 'UTF-8');
                $city_padezh = "в " . $city_word;
            }
            
            $item_name = str_replace('Обучение:', 'Обучение ' . $city_padezh . ':', $item_name);
            $item_name = str_replace('Медитация с поющими чашами', 'Медитация ' . $city_padezh . ' с поющими чашами', $item_name);
        }
    }
    
    // Определяем страницу Спасибо (Медитация или Интенсив)
    $return_slug = (strpos($item_name, 'Медитация') !== false) ? '/spasibo-meditation/' : '/spasibo-intensiv/';
    
    // Достаем почту организатора
    $organizer_email = get_field('organizer_email', $post_id); 
    if (empty($organizer_email)) {
        $organizer_email = 'trvl-analytics@yandex.ru'; 
    }

    // --- ОТПРАВЛЯЕМ УВЕДОМЛЕНИЕ ОРГАНИЗАТОРУ ПРИ ОТПРАВКЕ ФОРМЫ ---
    $emails_to_send = array_filter(array_map('trim', explode(',', $organizer_email)));
    $first_organizer_email = !empty($emails_to_send) ? reset($emails_to_send) : 'trvl-analytics@yandex.ru';
    
    $customer_comment = isset($_POST['customer_comment']) ? sanitize_textarea_field($_POST['customer_comment']) : '';

    $subject_lead = 'Новая заявка: ' . $item_name;
    
    $message_lead = "<h3>Поступила новая заявка на сайте (переход к оплате Продамус)</h3>";
    $message_lead .= "<b>Имя:</b> " . esc_html($customer_name) . "<br>";
    $message_lead .= "<b>Телефон:</b> " . esc_html($customer_phone) . "<br>";
    if (!empty($customer_email)) {
        $message_lead .= "<b>Еmail:</b> " . esc_html($customer_email) . "<br>";
    }
    $message_lead .= "<b>Сумма к оплате:</b> " . esc_html($item_price) . " руб.<br>";
    $message_lead .= "<b>Товар:</b> " . esc_html($item_name) . "<br>";
    
    if (!empty($customer_comment)) {
        $message_lead .= "<br><b>Комментарий:</b><br>" . nl2br(esc_html($customer_comment)) . "<br>";
    }

    // Добавляем все остальные поля формы (чекбоксы, радио-кнопки и т.д.)
    if (isset($_POST['all_fields']) && is_array($_POST['all_fields'])) {
        $message_lead .= "<br><hr><br><b>Все переданные данные формы:</b><br><br>";
        $message_lead .= "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%; max-width: 600px;'>";
        foreach ($_POST['all_fields'] as $field) {
            $f_name = sanitize_text_field($field['name']);
            $f_val = sanitize_textarea_field($field['value']);
            // Пропускаем технические поля, если нужно, или просто выводим всё
            if (!empty($f_val) && $f_name !== 'landing_id') {
                $message_lead .= "<tr><td style='background:#f9f9f9; width: 40%;'><b>" . esc_html($f_name) . "</b></td>";
                $message_lead .= "<td>" . nl2br(esc_html($f_val)) . "</td></tr>";
            }
        }
        $message_lead .= "</table>";
    }

    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    if (!empty($emails_to_send)) {
        wp_mail($emails_to_send, $subject_lead, $message_lead, $headers);
    }
    // --------------------------------------------------------------

    $data = array(
        'do' => 'pay',
        'sys' => 'website',
        'urlReturn' => home_url($return_slug . '?lid=' . $post_id), 
        'urlSuccess' => home_url($return_slug . '?lid=' . $post_id), 
        'urlNotification' => 'https://soundyoga.school/prodamus-webhook.php?token=soundyoga_secure_pay_8833',
        
        'customer_phone' => (string) $customer_phone,
        'customer_email' => (string) $customer_email,
        'customer_extra' => json_encode(array(
            'Имя' => mb_substr((string) $customer_name, 0, 50, 'UTF-8'),
            'Landing_ID' => (string) $post_id
        ), JSON_UNESCAPED_UNICODE),
        'products' => array(
            array(
                'name' => (string) $item_name,
                'price' => (string) $item_price,
                'quantity' => '1'
            )
        )
    );
    
    sy_prodamus_sort($data);
    $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
    $sign = hash_hmac('sha256', $json_data, $secret_key);
    
    $link = $domain . '?' . http_build_query($data) . '&signature=' . $sign;
    
    wp_send_json_success(array('link' => $link));
    wp_die();
}

// -------------------------------------------------------------------
// Асинхронная подгрузка данных (Даты, Время, Место) для страниц Спасибо
// Никаких правок в PHP шаблоны Webflow – всё инжектится через JS!
// -------------------------------------------------------------------
add_action('wp_ajax_get_ty_data', 'soundyoga_get_ty_data');
add_action('wp_ajax_nopriv_get_ty_data', 'soundyoga_get_ty_data');

function soundyoga_get_ty_data() {
    $post_id = isset($_POST['lid']) ? intval($_POST['lid']) : 0;
    $is_meditation = isset($_POST['is_meditation']) ? intval($_POST['is_meditation']) : 0;
    
    if (!$post_id) {
        wp_send_json_error('No Landing ID');
    }

    $c_dates = '[Даты уточняются]';
    $c_time = '[Время уточняются]';
    $c_address = '[Адрес уточняется]';

    // Адрес (из группы mesto_provedeniya)
    $mesto = get_field('mesto_provedeniya', $post_id);
    if ($mesto && !empty($mesto['adres_mesta_provedeniya'])) {
        $c_address = $mesto['adres_mesta_provedeniya'];
    }

    // Даты потока и Время
    $bron = get_field('bronirovanie', $post_id);
    if ($bron && !empty($bron['potoki_kursa'])) {
        $start = $bron['potoki_kursa'][0]['data_nachala_potoka'];
        $end = $bron['potoki_kursa'][0]['data_okonchaniya_potoka'];
        
        if ($start) {
            $months = ['01'=>'января', '02'=>'февраля', '03'=>'марта', '04'=>'апреля', '05'=>'мая', '06'=>'июня', '07'=>'июля', '08'=>'августа', '09'=>'сентября', '10'=>'октября', '11'=>'ноября', '12'=>'декабря'];
            $s_day = (int)substr($start, 6, 2); 
            $s_month = substr($start, 4, 2);
            
            if ($is_meditation) {
                // Для медитации: Дата проведения - это всегда первый день
                $c_dates = $s_day . ' ' . $months[$s_month];
                // Время: vremya_provedeniya в повторителе
                if (!empty($bron['potoki_kursa'][0]['blok_meditacii']['vremya_provedeniya'])) {
                    $c_time = $bron['potoki_kursa'][0]['blok_meditacii']['vremya_provedeniya'];
                }
            } else {
                // Для курса: полная дата
                if (!$end || $start === $end) {
                    $c_dates = $s_day . ' ' . $months[$s_month];
                } else {
                    $e_day = (int)substr($end, 6, 2); 
                    $e_month = substr($end, 4, 2);
                    $c_dates = ($s_month === $e_month) ? "$s_day-$e_day " . $months[$s_month] : "$s_day " . $months[$s_month] . " - $e_day " . $months[$e_month];
                }
                // Время курса
                $c_time = get_field('vremya_provedeniya_kursa', $post_id) ?: $c_time;
            }
        }
    }

    wp_send_json_success(array(
        'dates' => $c_dates,
        'time' => $c_time,
        'address' => $c_address
    ));
}

// =========================================================
// 5. ПОЛУЧЕНИЕ АКТУАЛЬНЫХ VAM-ИНТЕНСИВОВ ДЛЯ ФИЛЬТРА
// =========================================================

function sy_get_active_vam_tours($current_id = 0) {
    $today = date('Ymd');
    
    // Точный ключ ACF для даты начала ПЕРВОГО потока в повторителе
    $date_meta_key = 'bronirovanie_potoki_kursa_0_data_nachala_potoka';
    
    $args = array(
        'post_type'      => 'tour',
        'posts_per_page' => -1,
        'post__not_in'   => array($current_id),
        'tax_query'      => array(
            array(
                'taxonomy' => 'tour_type',
                'field'    => 'slug',
                'terms'    => 'vam',
            ),
        ),
        'meta_key'       => $date_meta_key,
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => array(
            array(
                'key'     => $date_meta_key,
                'compare' => '>=',
                'value'   => $today,
                'type'    => 'NUMERIC'
            ),
        ),
    );

    $tours_query = new WP_Query($args);

    $ru_months = [
        1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель', 
        5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август', 
        9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
    ];
    
    $active_months = array();

    if ($tours_query->have_posts()) {
        while ($tours_query->have_posts()) {
            $tours_query->the_post();
            // Получаем дату напрямую из базы по сложному ключу
            $date_val = get_post_meta(get_the_ID(), $date_meta_key, true);
            if ($date_val && strlen($date_val) >= 6) {
                $month_num = (int) substr($date_val, 4, 2);
                $active_months[$month_num] = $ru_months[$month_num];
            }
        }
        $tours_query->rewind_posts(); 
    }
    
    wp_reset_postdata();

    return array(
        'query'  => $tours_query,
        'months' => $active_months
    );
}

// ==========================================
// ПЕРЕХВАТ ПИСЕМ ФОРМЫ (для отправки организатору города)
// ==========================================
add_filter('wp_mail', 'sy_dynamic_form_email_override');
function sy_dynamic_form_email_override($args) {
    // Проверяем, что запрос идет через обработчик ajax форм (например, form-book-it)
    // Либо если передан landing_id в любом запросе ajax
    $landing_id = 0;
    
    // Ищем landing_id в $_POST (обычные поля) или в $_POST['data'] (если библиотека оборачивает данные)
    if (isset($_POST['landing_id'])) {
        $landing_id = intval($_POST['landing_id']);
    } elseif (isset($_POST['data']) && is_array($_POST['data']) && isset($_POST['data']['landing_id'])) {
        $landing_id = intval($_POST['data']['landing_id']);
    } elseif (isset($_POST['data']) && is_string($_POST['data'])) {
        parse_str($_POST['data'], $parsed_data);
        if (isset($parsed_data['landing_id'])) {
            $landing_id = intval($parsed_data['landing_id']);
        }
    }
    
    // Если есть ID лендинга, значит форма отправлена со страницы конкретного города
    if ($landing_id > 0) {
        $organizer_email = get_field('organizer_email', $landing_id);
        if (!empty($organizer_email)) {
            // Разбиваем по запятой, чистим пробелы — отправляем всем
            $emails_arr = array_filter(array_map('sanitize_email', array_map('trim', explode(',', $organizer_email))));
            if (!empty($emails_arr)) {
                $args['to'] = $emails_arr;
            }
        }
    }
    
    return $args;
}

// ==========================================
// АКТУАЛИЗАЦИЯ ЦЕН В ОБХОД КЭША (AJAX)
// ==========================================
add_action('wp_ajax_get_tour_prices', 'ajax_get_tour_prices');
add_action('wp_ajax_nopriv_get_tour_prices', 'ajax_get_tour_prices');

function ajax_get_tour_prices() {
    $post_id = isset($_POST['landing_id']) ? intval($_POST['landing_id']) : 0;
    if (!$post_id) {
        wp_send_json_error();
    }

    $response = array(
        'final_price' => 0,
        'old_price' => 0,
        'deadline_text' => '',
        'has_discount' => false,
        'skidka_percent' => 0
    );

    if (have_rows('bronirovanie', $post_id)) {
        while (have_rows('bronirovanie', $post_id)) {
            the_row();
            $cena_kursa = (int) get_sub_field('cena_kursa');
            if (!$cena_kursa) break;
            
            $finalnaya_cena = $cena_kursa;
            $current_date = wp_date('Ymd');
            $periody = get_sub_field('periody_cen'); 

            if (!empty($periody) && is_array($periody)) {
                foreach ($periody as $period) {
                    if ($period['data_perioda'] >= $current_date) {
                        $finalnaya_cena = (int) $period['cena_perioda']; 
                        break;
                    }
                }
            }
            
            $response['final_price'] = $finalnaya_cena;
            $response['old_price'] = $cena_kursa;
            
            if ($finalnaya_cena < $cena_kursa) {
                $response['has_discount'] = true;
                $response['skidka_percent'] = round(100 - (($finalnaya_cena / $cena_kursa) * 100));
                
                if (!empty($periody) && is_array($periody)) {
                    foreach ($periody as $period) {
                        if ($period['data_perioda'] >= $current_date) {
                            $response['deadline_text'] = wp_date('j F', strtotime($period['data_perioda']));
                            break;
                        }
                    }
                }
            }
            break; 
        }
    }
    
    wp_send_json_success($response);
}

// ==========================================
// Меняем название записи на новости
function rename_posts_to_news() {
    global $wp_post_types;

    // Получаем объект типа записи "post"
    $post_type = $wp_post_types['post'];

    // Меняем названия
    $post_type->labels->name = 'Новости';
    $post_type->labels->singular_name = 'Новость';
    $post_type->labels->add_new = 'Добавить новость';
    $post_type->labels->add_new_item = 'Добавить новость';
    $post_type->labels->edit_item = 'Редактировать новость';
    $post_type->labels->new_item = 'Новая новость';
    $post_type->labels->view_item = 'Просмотреть новость';
    $post_type->labels->search_items = 'Искать новости';
    $post_type->labels->not_found = 'Новостей не найдено';
    $post_type->labels->not_found_in_trash = 'Новостей в корзине не найдено';
    $post_type->labels->all_items = 'Все новости';
    $post_type->labels->menu_name = 'Новости';
    $post_type->labels->name_admin_bar = 'Новость';
}

add_action('init', 'rename_posts_to_news');

// ==========================================
//добавляем кнопку межстрочного интервала
add_filter('mce_buttons_2', 'custom_line_height_button');
function custom_line_height_button($buttons) {
    array_push($buttons, 'lineheight');
    return $buttons;
}

add_filter('mce_external_plugins', 'custom_line_height_plugin');
function custom_line_height_plugin($plugins) {
    $plugins['lineheight'] = get_stylesheet_directory_uri() . '/js/line-height.js';
    return $plugins;
}

add_action('admin_enqueue_scripts', 'load_dashicons_admin');
function load_dashicons_admin() {
    wp_enqueue_style('dashicons');
}

// Подключаем Dashicons (если иконка не отображается)
add_action('admin_enqueue_scripts', 'load_dashicons');
function load_dashicons() {
    wp_enqueue_style('dashicons');
}

// Отключение масштабирования (зума) страницы на мобильных устройствах
add_action('wp_head', function() {
    ?>
    <script>
    (function() {
        const viewport = document.querySelector('meta[name="viewport"]');
        if (viewport) {
            viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
        }
    })();
    </script>
    <?php
}, 1); // Приоритет 1, чтобы скрипт встал как можно выше в <head>