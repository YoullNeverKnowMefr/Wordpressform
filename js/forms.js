// FORMS

if (typeof wtw_forms_init_custom === 'undefined') {
    wtw_forms_init();
}

function wtw_forms_init() {

    const forms = document.querySelectorAll('form');

    if (forms.length) {

        window.wtw_form_submiting = false;

        forms.forEach(el => {

            const bindSelectors = (typeof wtw_forms !== 'undefined' && Array.isArray(wtw_forms['__forms'])) ? wtw_forms['__forms'] : [];
            let matched = false;
            for (let i = 0; i < bindSelectors.length; i++) {
                try {
                    if (el.matches(bindSelectors[i])) { matched = true; break; }
                } catch (e) {}
            }
            if (!matched) return;

            if (el.getAttribute('action') !== null) return;

            el.setAttribute('action', '/');

            el.querySelectorAll('[data-name]').forEach(el => {
                el.setAttribute('name', el.getAttribute('data-name'));
            })

            addHiddenRecaptchaField(wtw_forms['__google_recaptcha']);

            var inputs = [
                { name: '__page', value: location.origin + location.pathname },
                { name: '__title', value: document.title },
                { name: '__form', value: el.dataset.name },
                { name: '__query', value: document.location.search }
            ];

            inputs.forEach(function (attrs) {
                var input = document.createElement("input");
                input.type = "hidden";
                input.name = attrs.name;
                input.value = attrs.value;
                el.appendChild(input);
            });

            const event = new CustomEvent('forms-before-send');

            if (typeof VALIDATE_DATA !== 'undefined' && VALIDATE_DATA.length) {

                const validation = new JustValidate(el);
            
                VALIDATE_DATA.forEach(setting => {
                    const { selector, rules } = setting;
            
                    const fieldRules = rules.map(rule => {
                        const ruleConfig = {
                            rule: rule.rule,
                            errorMessage: rule.error_message
                        };
            
                        const rules_values = [
                            'min_length',
                            'max_length',
                            'min_number',
                            'max_number',
                            'min_files_count',
                            'max_files_count',
                        ];
            
                        rules_values.forEach(value => {
                            if (rule[value] !== undefined && rule[value].length)
                                ruleConfig.value = parseInt(rule[value]);
                        });
            
                        if (rule['custom_regexp'].length)
                            ruleConfig.value = rule['custom_regexp'];
                            
                        if (rule.rule === 'files') {
                            ruleConfig.value = {
                                files: {
                                    extensions: rule['extensions'].split(',').map(ext => ext.trim()),
                                    types: rule['types'].split(',').map(ext => ext.trim()),
                                    minSize: rule['min_size'],
                                    maxSize: rule['max_size'],
                                },
                            }
                        }
            
                        return ruleConfig;
                    });
            
                    if (el.querySelector(selector) !== null)
                        validation.addField(selector, fieldRules);
                });
            
                // ОБЪЕДИНЕННЫЙ обработчик onSuccess
                validation.onSuccess(async (event) => {
                    event.preventDefault();
                    if (window.wtw_form_submiting) return;
                    const form = event.target;
                    const ok = await (window.sfaCaptcha && window.sfaCaptcha.validate ? window.sfaCaptcha.validate(form) : Promise.resolve(true));
                    if (!ok) return;
                
                    // Если капчи нет или она пройдена — отправляем форму
                    setCaptchaField(form);
                    wto_form_send(form, get_form_extentions(form));
                });



            
            } else {
            
                el.addEventListener('submit', async function (e) {
                    e.preventDefault();
            
                    if (window.wtw_form_submiting) return;
                    const ok = await (window.sfaCaptcha && window.sfaCaptcha.validate ? window.sfaCaptcha.validate(el) : Promise.resolve(true));
                    if (!ok) return;
                    setCaptchaField(el);
                    wto_form_send(el, get_form_extentions(el));
                })
            }

        })
    }
}

function setCaptchaField(el) {
    const recaptcha_response_field = el.querySelector('[name=__recaptcha_response]');
    if (recaptcha_response_field !== null) {
        grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'submit' }).then(function (token) {
            recaptcha_response_field.value = token;
        });
    }
}

function get_form_extentions(el) {

    let ids = {};

    for (extention in wtw_forms) {
        wtw_forms[extention].forEach((selector, index) => {
            if (el.matches(selector)) {
                ids[extention] = index;
            }
        });
    }

    return ids;
}

function addHiddenRecaptchaField(selectors) {

    if (selectors === undefined) return null;

    function addHiddenFieldToForm(form) {
        if (!form.querySelector('input[name="__recaptcha_response"]')) {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = '__recaptcha_response';
            form.appendChild(hiddenField);
        }
    }

    selectors.forEach(selector => {
        const forms = document.querySelectorAll(selector);
        forms.forEach(form => {
            addHiddenFieldToForm(form);
        });
    });
}

function wto_form_send(el, ids) {

    const loading_el = el.querySelector('[data-bind="loading"]');
    if (loading_el !== null) {
        loading_el.style.display = 'block';
    } else {
        el.style.opacity = '0.3';
    }

    window.wtw_form_submiting = true;

    const data = { foo: el, ...ids };

    ajaxs('ajaxs_wtw_mail_sent', data,

        function (result) {

            window.wtw_form_submiting = false;

            let form_setup = null;

            const succes_el = el.parentElement.querySelector('.w-form-done');
            const error_el = el.parentElement.querySelector('.w-form-fail');

            if (result.data !== undefined) {
                form_setup = result.data;
            }

            if (result.success && form_setup !== null) {

                const event = new CustomEvent('forms-after-send', {
                    detail: {
                        form_setup: form_setup,
                    }
                });

                const hide_duration = form_setup['hide_duration'] ? parseInt(form_setup['hide_duration']) : 1000;

                document.dispatchEvent(event);

                if (form_setup.redirect) {
                    if (form_setup.redirect_new_tab) {
                        window.open(form_setup.redirect_url);
                    } else {
                        location.href = form_setup.redirect_url;
                    }
                    return;
                }

                el.reset();

                if (loading_el !== null) {
                    loading_el.style.display = 'none';
                } else {
                    el.style.opacity = '1';
                }

                document.querySelectorAll('[data-files-list]').forEach(el => {
					fadeDown(el, hide_duration);
                });

                if (succes_el === null) return;

                if (form_setup.success_message !== '') {
                    if (succes_el.querySelector('div') != null) {
                        succes_el.querySelector('div').innerHTML = form_setup.success_message;
                    } else {
                        succes_el.innerHTML = form_setup.success_message;
                    }
                }

                succes_el.style.display = 'block';
                error_el.style.display = 'none';

                if (form_setup.hide) {
                    el.style.display = 'none';
                }

                if (form_setup.delay !== undefined && form_setup.delay != null && form_setup.delay.length) {

                    delay = parseInt(form_setup.delay) * 1000;

                    setTimeout(() => {
                        el.removeAttribute('style');
						fadeDown(succes_el, hide_duration);
                        document.body.style.overflow = "visible";

                        if (form_setup.lbox_hide.length) {
                            lbox_el = document.querySelector(form_setup.lbox_hide);
                            if (lbox_el !== undefined) {
								fadeDown(lbox_el, hide_duration);
                            }
                        }

                    }, delay)

                }

            } else {

                let error_message = 'Ошибка отправки!<br>Попробуйте позже.';

                if (result.data !== undefined && result?.data?.error_message != undefined) {
                    error_message = result.data.error_message;
                } else {
                    error_message = result.data;
                }

                if (error_el === null) {
                    console.log(error_message);
                } else {
                    if (error_el.querySelector('div')) {
                        error_el.querySelector('div').innerHTML = error_message;
                    } else {
                        error_el.innerHTML = error_message;
                    }

                    error_el.style.display = 'block';
                    succes_el.style.display = 'none';
                }

            }

        }
    );
}
					
const fadeDown = (el, timeout) => {
      el.style.opacity = 1;
      el.style.transition = `opacity ${timeout}ms`;
      el.style.opacity = 0;

      setTimeout(() => {
        el.style.display = 'none';
      }, timeout);
    }
