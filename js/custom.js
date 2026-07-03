// ==============================
// 1) Инициализация маски телефонов (БЕЗОПАСНАЯ)
// ==============================
function initPhoneMask() {
    try {
        if (typeof window.intlTelInput === 'undefined') return;
        
        const phoneInputs = document.querySelectorAll('[data-name="Телефон"], [name="Телефон"]');
        phoneInputs.forEach(function(input) {
            const existingIti = (window.intlTelInputGlobals && typeof window.intlTelInputGlobals.getInstance === 'function')
                ? window.intlTelInputGlobals.getInstance(input) : null;
            if (existingIti) return;

            window.intlTelInput(input, {
                initialCountry: "ru",
                separateDialCode: true, 
                loadUtils: () => import("/wp-content/themes/sound-yoga_1774938209/js/utils.js"),
            });

            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^\d\s-]/g, '');
            });
        });
    } catch(e) { 
        console.error('Ошибка инициализации маски:', e); 
    }
}
document.addEventListener("DOMContentLoaded", initPhoneMask);

// ==============================
// 2) Интеграция с Web-Steps 
// ==============================
(function waitForInit() {
    if (typeof window.wtw_webflow_init === "function") {
        window.wtw_webflow_init();
    } else {
        setTimeout(waitForInit, 50);
    }
})();

// ==============================
// 3) ОСНОВНАЯ ЛОГИКА ШАБЛОНА И ПРОДАМУС
// ==============================
jQuery(document).ready(function($) {

    // --- Навигация по табам и скролл ---
    function handleNavigation() {
        var hash = window.location.hash;
        if (hash) {
            var $target = $(hash);
            var isDataHash = hash.indexOf('#Data-') !== -1;
            
            // Если хэш Data-1, ищем таб по data-w-tab
            var $tabLink = $('.w-tab-link[href="' + hash + '"]');
            if (!$tabLink.length && isDataHash) {
                var tabIndex = hash.replace('#Data-', '');
                $tabLink = $('.w-tab-link[data-w-tab="Tab ' + tabIndex + '"]');
            }

            if ($target.length || $tabLink.length) {
                var isTabPanel = $target.length ? $target.hasClass('w-tab-pane') : false;

                // Если это Таб
                if (isTabPanel || $tabLink.length) {
                    $tabLink.trigger('click');
                    
                    // Блокируем скролл для Data- (чтобы не прыгало)
                    if (isDataHash) {
                        // В webflow скролл может стриггериться нативно, 
                        // поэтому небольшой хак: 
                        if ($(window).scrollTop() > 0 && $(window).scrollTop() < 300) {
                            window.scrollTo(0,0);
                        }
                        return;
                    } else {
                        // Для обычных Tab-1, Tab-2 и остальных -> СКРОЛЛИМ К БЛОКУ
                        var scrollTarget = $target.length ? $target : $tabLink;
                        $('html, body').animate({ scrollTop: scrollTarget.offset().top - 100 }, 500);
                    }
                } 
                // Если это обычный якорь (не таб)
                else if (!isTabPanel && $target.length && !isDataHash) {
                    $('html, body').animate({ scrollTop: $target.offset().top - 100 }, 500);
                }
            }
        }
    }
    
    // --- Закрытие дропдауна городов по крестику ---
    $(document).on('click', '.close-chose-city-drop', function() {
        var $dropdown = $(this).closest('.w-dropdown');
        if ($dropdown.length) {
            $dropdown.triggerHandler('w-close');
        } else {
            // Запасной вариант: скрываем список напрямую
            var $list = $(this).closest('.w-dropdown-list');
            if ($list.length) {
                $list.css('opacity', '0');
                setTimeout(function() { $list.css('display', 'none'); }, 300);
            }
        }
    });

    // Перехват клика по Data- ссылкам для плавного переключения без прыжков
    $('a[href^="#Data-"]').on('click', function(e) {
        e.preventDefault();
        
        // Закрываем вебфлоу-дропдаун, так как мы отменили дефолтный переход
        $(this).closest('.w-dropdown').triggerHandler('w-close');

        var hash = $(this).attr('href');
        if(history.pushState) {
            history.pushState(null, null, hash);
        } else {
            window.location.hash = hash;
        }
        handleNavigation();
    });

    // Синхронизация интерфейса: даты и цены при переключении табов
    function syncTabUi(tabIndex) {
        var $dropdownLink = $('.hero-date-link[href="#Data-' + tabIndex + '"]');
        if ($dropdownLink.length) {
            // 1. Хедер (короткая дата)
            var shortDate = $dropdownLink.attr('data-short-date');
            if (shortDate) {
                $('#header-dynamic-date').text(shortDate);
            }
            // 2. Дропдаун на главном экране (полная дата)
            var fullDateText = $dropdownLink.find('.first_date').text();
            if (fullDateText) {
                $('.dropdown-date.w-dropdown-toggle .first_date').text(fullDateText);
            }
        }

        // 3. Цена внизу (bottom-pay_price-box)
        var $pane = $('.w-tab-pane[data-w-tab="Tab ' + tabIndex + '"]');
        if ($pane.length) {
            var priceText = $pane.find('.target_box-tab-pane.price #final_price, .target_box-tab-pane.price .target_price').first().text();
            if (priceText) {
                $('.bottom-pay_price-box .target_price.bottom').text(priceText);
            }
        }
    }

    $(document).on('click', '.w-tab-link', function() {
        var tabName = $(this).attr('data-w-tab');
        if (tabName) {
            syncTabUi(tabName.replace('Tab ', ''));
        }
    });

    $(window).on('load', function() {
        setTimeout(function() {
            var activeTab = $('.w-tab-link.w--current').attr('data-w-tab');
            if (activeTab) {
                syncTabUi(activeTab.replace('Tab ', ''));
            }
        }, 500);
    });

    // Сохраняем актуальные цены глобально
    window.currentPrices = {
        course: 0,
        meditationSingle: 0,
        meditationCouple: 0
    };

    function formatPrices(p) {
        return parseInt(p, 10).toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    }

    function updateMeditationPrice() {
        var val = $('input[data-name="Билеты_на_медитацию"]:checked').val() || "1";
        if (val === "2") {
            if (window.currentPrices.meditationCouple > 0) {
                $('#display_meditation_price').text(formatPrices(window.currentPrices.meditationCouple));
            }
        } else {
            if (window.currentPrices.meditationSingle > 0) {
                $('#display_meditation_price').text(formatPrices(window.currentPrices.meditationSingle));
            }
        }
    }

    function updateCoursePrice() {
        var val = $('input[data-name="Тип_оплаты"]:checked').val() || "100";
        var coursePrice = window.currentPrices.course;
        if (coursePrice > 0) {
            if (val === "50") {
                $('#display_final_price').text(formatPrices(Math.round(coursePrice / 2)));
            } else if (val === "repass") {
                $('#display_final_price').text(formatPrices(18000));
            } else {
                $('#display_final_price').text(formatPrices(coursePrice));
            }
        }
    }

    // Обработка клика по кнопке открытия формы Продамуса, чтобы подставить цены
    $('.open-prodamus-popup').on('click', function() {
        var price = parseInt($(this).attr('data-price')) || 0;
        var couplePrice = parseInt($(this).attr('data-couple-price')) || 0;
        var type = $(this).attr('data-type');
        
        if (type === 'meditation') {
            window.currentPrices.meditationSingle = price;
            window.currentPrices.meditationCouple = couplePrice;
            updateMeditationPrice();
        } else if (type === 'course') {
            window.currentPrices.course = price;
            updateCoursePrice();
        }
    });

    $('input[data-name="Билеты_на_медитацию"]').on('change', updateMeditationPrice);
    $('input[data-name="Тип_оплаты"]').on('change', updateCoursePrice);
    
    // Дублируем на клик по обёрткам, так как Webflow иногда скрывает события
    $('.payment-type-wrapper, .w-radio').on('click', function() {
        setTimeout(function() {
            updateMeditationPrice();
            updateCoursePrice();
        }, 50);
    });
    
    $(window).on('hashchange', handleNavigation);
    
    // Ждем 500мс, чтобы Webflow успел прогрузить и сбросить свои табы
    setTimeout(handleNavigation, 500);
    
    // Для 100% надежности дублируем проверку после полной загрузки всех элементов страницы
    $(window).on('load', function() {
        setTimeout(handleNavigation, 100);
    });

    // --- Скролл-шапка ---
    $(window).on('scroll', function() {
        if ($(window).scrollTop() > 50) {
            $('.header').addClass('scrolled');
        } else {
            $('.header').removeClass('scrolled');
        }
    });

    // --- Selectize ---
    if ($.fn.selectize) {
        $('.dropdown-city select').selectize({
            create: false,
            sortField: 'text'
        });
    }

    // --- Слайдеры ---
    if ($.fn.slick) {
        $('.slider-teachers').slick({
            infinite: true,
            slidesToShow: 3,
            slidesToScroll: 1,
            responsive: [
                { breakpoint: 992, settings: { slidesToShow: 2 } },
                { breakpoint: 768, settings: { slidesToShow: 1 } }
            ]
        });

        $('.slider-reviews').slick({
            infinite: true,
            slidesToShow: 2,
            slidesToScroll: 1,
            responsive: [
                { breakpoint: 768, settings: { slidesToShow: 1 } }
            ]
        });
    }

    // --- SimpleBar (Кастомные скроллбары) ---
    if (typeof SimpleBar !== 'undefined') {
        $('[data-simplebar]').each(function() {
            new SimpleBar(this);
        });
    }

    // --- Модальные окна ---
    $('.btn-modal').on('click', function(e) {
        e.preventDefault();
        var targetModal = $(this).attr('data-modal') || $(this).attr('href');
        if (targetModal && $(targetModal).length) {
            $(targetModal).fadeIn(300).css('display', 'flex');
            $('body').css('overflow', 'hidden'); 
        }
    });

    $('.btn-close, .modal-bg').on('click', function(e) {
        if ($(e.target).hasClass('modal-bg') || $(e.target).closest('.btn-close').length) {
            e.preventDefault();
            $(this).closest('.modal-wrapper').fadeOut(300);
            $('body').css('overflow', ''); 
        }
    });

    // Спец. класс для закрытия модалки медитации
    $('.close-modal-meditation, .section-modal-meditation').on('click', function(e) {
        if ($(e.target).hasClass('section-modal-meditation') || $(e.target).closest('.close-modal-meditation').length) {
            e.preventDefault();
            $('.section-modal-meditation').fadeOut(300);
            $('body').css('overflow', ''); 
        }
    });

    // --- Динамический дедлайн ---
    $('.js-dynamic-deadline').each(function() {
        var $el = $(this);
        var rawData = $el.attr('data-deadlines');
        if (!rawData) return;
        try {
            var dates = JSON.parse(rawData);
            var now = new Date();
            var todayStr = now.getFullYear() + ('0' + (now.getMonth() + 1)).slice(-2) + ('0' + now.getDate()).slice(-2);
            var nextDateStr = null;
            for (var i = 0; i < dates.length; i++) {
                if (dates[i] >= todayStr) { nextDateStr = dates[i]; break; }
            }
            if (nextDateStr) {
                var targetYear = parseInt(nextDateStr.substring(0, 4), 10);
                var targetMonth = parseInt(nextDateStr.substring(4, 6), 10) - 1;
                var targetDay = parseInt(nextDateStr.substring(6, 8), 10);
                var targetDate = new Date(targetYear, targetMonth, targetDay);
                var diffTime = targetDate - now;
                var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                if (diffDays > 0) {
                    $el.text('До повышения цены ' + diffDays + ' ' + declOfNum(diffDays, ['день', 'дня', 'дней']));
                } else if (diffDays === 0) {
                    $el.text('Цена повысится завтра');
                }
            }
        } catch (e) { console.error('Ошибка дедлайна', e); }
    });

    function declOfNum(n, text_forms) {  
        n = Math.abs(n) % 100; var n1 = n % 10;
        if (n > 10 && n < 20) { return text_forms[2]; }
        if (n1 > 1 && n1 < 5) { return text_forms[1]; }
        if (n1 == 1) { return text_forms[0]; }
        return text_forms[2];
    }
 
    // ==========================================
    // 4) ИНТЕГРАЦИЯ С ПРОДАМУСОМ (ТОЛЬКО НАШИ ФОРМЫ)
    // ==========================================
    $('#form-course, #form-meditation').on('submit', function(e) {
        
        // Жестко останавливаем Webflow и другие скрипты
        e.preventDefault();
        e.stopImmediatePropagation();

        var $form = $(this);
        var submitBtn = $form.find('input[type="submit"]');
        var originalText = submitBtn.val();
        submitBtn.val('Формируем ссылку...').prop('disabled', true);

        var isMeditation = $form.attr('id') === 'form-meditation';
        var itemName = isMeditation ? 'Медитация с поющими чашами' : 'Обучение: Интенсив ВАМ';

        if (isMeditation) {
            var ticketType = $('input[data-name="Билеты_на_медитацию"]:checked').val();
            if (ticketType === "2") {
                itemName += ' (Парный билет)';
            } else {
                itemName += ' (Один билет)';
            }
        } else {
            var payType = $('input[data-name="Тип_оплаты"]:checked').val();
            if (payType === "50") {
                itemName += ' (Предоплата 50%)';
            } else if (payType === "repass") {
                itemName += ' (Повторное прохождение)';
            } else {
                itemName += ' (Оплата 100%)';
            }
        }

        // Берем цену напрямую из HTML-текста, который видит клиент
        var priceSpan = isMeditation ? $('#display_meditation_price') : $('#display_final_price');
        var priceText = priceSpan.text().replace(/\s/g, '').replace(/[^\d]/g, '');
        var finalPrice = parseInt(priceText, 10) || 0;

        // Получаем правильный телефонный номер в международном формате из маски
        var phoneInput = $form.find('input[data-name="Телефон"]')[0];
        var finalPhone = phoneInput ? phoneInput.value : '';
        if (phoneInput && window.intlTelInputGlobals && typeof window.intlTelInputGlobals.getInstance === 'function') {
            var iti = window.intlTelInputGlobals.getInstance(phoneInput);
            if (iti) {
                var fullNum = iti.getNumber();
                if (fullNum) finalPhone = fullNum;
            }
        }

        var formData = {
            action: 'get_prodamus_link',
            customer_name: $form.find('input[data-name="Имя"]').val() || 'Участник',
            customer_phone: finalPhone,
            customer_email: $form.find('input[data-name="Email"], input[type="email"]').val() || '',
            customer_comment: $form.find('textarea[data-name="Комментарий"], input[data-name="Комментарий"]').val() || '',
            landing_id: $form.find('input[name="landing_id"]').val() || '',
            item_price: finalPrice,
            item_name: itemName,
            all_fields: $form.serializeArray()
        };

        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success && response.data.link) {
                    window.location.href = response.data.link;
                } else {
                    alert('Ошибка генерации ссылки.');
                    submitBtn.val(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('Ошибка соединения с сервером. Попробуйте позже.');
                submitBtn.val(originalText).prop('disabled', false);
            }
        });
    });

});

// ==========================================
// 5) ФИЛЬТР МЕСЯЦЕВ ДЛЯ АКТУАЛЬНЫХ ТУРОВ
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.filter-month');
    const tourCards = document.querySelectorAll('.loc-tour_town-card');

    if (!filterBtns.length || !tourCards.length) return;

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const filterMonth = this.getAttribute('data-filter');

            tourCards.forEach(card => {
                const cardMonth = card.getAttribute('data-month');

                if (filterMonth === 'all' || filterMonth === cardMonth) {
                    card.style.display = ''; 
                    setTimeout(() => { card.style.opacity = '1'; }, 10);
                } else {
                    card.style.opacity = '0';
                    setTimeout(() => { card.style.display = 'none'; }, 300);
                }
            });
        });
    });
});

// ==========================================
// 6) АВТОЗАПОЛНЕНИЕ СТРАНИЦ СПАСИБО ИЗ ACF
// ==========================================
document.addEventListener("DOMContentLoaded", function() {
    var urlParams = new URLSearchParams(window.location.search);
    var lid = urlParams.get('lid');
    var path = window.location.pathname;

    if (lid && (path.indexOf('spasibo-') !== -1)) {
        var isMeditation = path.indexOf('spasibo-meditation') !== -1 ? 1 : 0;
        
        jQuery.ajax({
            url: '/wp-admin/admin-ajax.php', 
            type: 'POST',
            data: {
                action: 'get_ty_data',
                lid: lid,
                is_meditation: isMeditation
            },
            success: function(res) {
                if (res.success && res.data) {
                    // Ищем элементы с соответствующими id или class и подменяем текст
                    var updateText = function(selector, val) {
                        var el = document.querySelector(selector);
                        if (el) el.innerHTML = val;
                    };
                    
                    // По ID
                    updateText('#ty_date', res.data.dates);
                    updateText('#ty_time', res.data.time);
                    updateText('#ty_address', res.data.address);
                    
                    // Или по классу
                    var dateEls = document.querySelectorAll('.ty_date');
                    dateEls.forEach(function(el) { el.innerHTML = res.data.dates; });
                    
                    var timeEls = document.querySelectorAll('.ty_time');
                    timeEls.forEach(function(el) { el.innerHTML = res.data.time; });
                    
                    var addrEls = document.querySelectorAll('.ty_address');
                    addrEls.forEach(function(el) { el.innerHTML = res.data.address; });
                }
            }
        });
    }
});

// ==========================================
// 7) ДИНАМИЧЕСКИЕ ЦЕНЫ (ОБХОД КЭША WPFC)
// ==========================================
document.addEventListener("DOMContentLoaded", function() {
    var landingIdInput = document.getElementById('landing_id');
    if (landingIdInput && landingIdInput.value) {
        jQuery.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'get_tour_prices',
                landing_id: landingIdInput.value
            },
            success: function(res) {
                if (res.success && res.data && res.data.final_price > 0) {
                    var data = res.data;
                    var finalPriceFmt = new Intl.NumberFormat('ru-RU').format(data.final_price) + '₽';
                    
                    // Обновляем текст цен
                    jQuery('#final_price').text(finalPriceFmt);
                    jQuery('.target_price.bottom').text(finalPriceFmt);
                    jQuery('#display_final_price').text(new Intl.NumberFormat('ru-RU').format(data.final_price));
                    
                    // Обновляем data-атрибуты для продамуса
                    jQuery('.tab-order.open-prodamus-popup').attr('data-price', data.final_price);
                    
                    if (data.has_discount) {
                        jQuery('.target_price-old').text(new Intl.NumberFormat('ru-RU').format(data.old_price) + '₽').show();
                        jQuery('.dedline-txt').text(data.deadline_text.toLowerCase());
                        jQuery('.target_price-date:contains("при")').show();
                        jQuery('.sale-wrap div').text(data.skidka_percent + '%');
                        jQuery('.sale-wrap').show();
                    } else {
                        jQuery('.target_price-old').hide();
                        jQuery('.target_price-date:contains("при")').hide(); // Скрываем только блок с датой
                        jQuery('.sale-wrap').hide();
                    }
                }
            }
        });
    }
});