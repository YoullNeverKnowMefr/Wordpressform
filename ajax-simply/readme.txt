=== AJAX Simply ===

Stable tag:        trunk
Tested up to:      5.5
Plugin URI:        https://wp-kama.ru/id_9054/ajax-simply.html
Requires at least: 3.0.1
Requires PHP:      5.6
License:           GPL3
License URI:       http://www.gnu.org/licenses/gpl-3.0.html
Contributors:      Kama
Tags:              ajax, client side, server side, xmlrequest, wordpress, request, response


Allows to create AJAX applications on WordPress by simple way.



== Description ==

Plugin implements data escaping, form data collection and so no...




= Documentation =

Please refer [Documentation](https://wp-kama.ru/?p=9054).




== Installation ==

1. Upload and unpack `ajax-simply` folder with all files to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress



== Frequently Asked Questions ==

= Where can I put some notices, comments or bugreports? =

Do not hesistate to write me at [this page](https://wp-kama.ru/?p=9054).




== Screenshots ==

1. A screenshot of simple example



== Changelog ==

= 1.5.3 =
* NEW: Only clicked `input[type="submit"]` value will appear in form data ().

= 1.5.2 =
* IMP: `:focus` added to `button` selector. See prev release 1.5.1.

= 1.5.1 =
* NEW: `<button>` tag added when collecting `name=>value` form data.

= 1.5.0 =
* FIX: Correct call of the `ajaxs_done` action for `ajaxs(...).done(func)`. The same for `ajaxs_fail`, `ajaxs_always`. Example: when we use `ajaxs(...).done(func)` the action `ajaxs_done` was called and then `func` function, bun it should vice versa.
* FIX: Чуть более стабильная работа логики вывода консоли в HTML из версии 1.4.8.
* FIX: мини баг с передачей файлов в параметры, такой вариант работал некорректно: `ajaxs( 'phpfunc', { files: $form.find('[name="files[]"]')[0].files } )`.

= 1.4.9 =
* FIX: prev release bug.

= 1.4.8 =
* NEW: Add `<div id="#ajaxs-console">` or `<div class=".ajaxs-console>` HTML elements to obtain console output directly on page, for example to show PHP errors.

= 1.4.5 =
* NEW: Parameter `skip_basic_nonce` - `ajaxs( 'funcname', { skip_basic_nonce:1 } )`. It allows to skip basic nonce check for single request when such check is turned on in options.

= 1.4.4 =
* NEW: New method `$jx->jscode()` alias for `$jx->jseval()`.

= 1.4.3 =
* NEW: Third parameter for `$jx->html( $selector, $html, $method )` method it allows manipulate passed HTML. For example now you can append passed HTML. All available values for the parameter: html(by default)|append|prepend|replace|before|after
* NEW: Methods: `$jx->append()`, `$jx->prepend()`, `$jx->before()`, `$jx->after()`, `$jx->replace()`.

= 1.4.2 =
* IMPROVE: Group PHP error in console.

= 1.4.1 =
* BUG: Bug when grouping to console and pass zero in second parameter: `$jx->log( 'foo', 0 )`.

= 1.4.0 =
* NEW: `doing_ajaxs()` function to Determine whether the current request is a Ajax Simply request.
* NEW: Move to PHP 5.6 support only: use in code short syntax of arrays, use `...$params` in function arguments.
* NEW: `console.group()` - now You can group console.log() calls by specifiing many arguments in `jx::log()` method: `$jx->log( one, two, three )`.

= 1.3.9 =
* NEW: Method `jx::refresh()` - alias for `jx::reload()`.

= 1.3.8 =
* FIX: If there is no inputs with `name` attr, use `id` or `data-name` attribute as name.

= 1.3.7 =
* FIX: Не в мультисайте, из-за неявной работы функции `update_site_option()` опция плагина добавлялась без флага autoload.

= 1.3.6 =
* NEW: Мини поиск файла `wp-load.php` для файла обработчика AJAX, который доступен в самом плагине. Теперь этот файл можно не создавтаь, а просто поставить галочку в настройках плагина.

= 1.3.5 =
* FIX: Баг при указании вложенной фукнции в `$jx->call()` например `window.myobject.myfunc`.

= 1.3.4 =
* ADD: Если в ajaxs передан элемент формы, но в нем не нашлось ни одного поля с атрибутом 'name', то вместо 'name' у полей будет взят атрибут 'id'.

= 1.3.3 =
* IMPROVE: Дополнена очистка опций при сохранении в админке.

= 1.3.2 =
* IMPROVE: неправильно подключался файл перевода для страницы настроек, когда плагин устанавливается в MU каталог (mu-plugins).

= 1.3.1 =
* NEW: js функция `ajaxsURL( action )` для удобного получения ajax URL, когда нужно отправить запрос на плагин не через встроенную фукнцию плагина ajaxs(). Например для плагина `jQuery.validate()`. Параметры при этом нужно передавать в POST данных.

= 1.3.0 =
* NEW: теперь в параметре `$data` в `$jx->done($data)` или `$jx->error($data)` можно передать WP_Error объект, он будет обработан. `$data` в итоге превратиться в строку, со всеми сообщениями об ошибках, которые были в WP_Error объекте. При этом `$jx->done()` превратиться в `$jx->error()`, потому что в данных есть ошибка...

= 1.2.9.1 =
* NEW: Добавил вывод сообщений ошибок в консоль при нерабочем nonce коде или для хука ajaxs_allow_process. А то иногда непонятно почему не срабатывает запрос...

= 1.2.9 =
* ADD: Проверка подключенного jquery скрипта. Если jquery нет, он подключается автоматически с предупреждением об этом в консоли.

= 1.2.8 =
* NEW: параметр 'data.largeFileError' для data параметра фукнции ajaxs(). Вместе с ним новые опции, для установки максимального размера загружаемого файла. Эти опции можно установить через фильтры: `ajaxs_post_max_size` и `ajaxs_upload_max_filesize`.
* Поправлены мелкие баги в JS

= 1.2.7.1 =
* БАГ: при преобразовании строк в числа.

= 1.2.7 =
* NEW: в параметр data передавемый в AJAX собитии добавлены параметры action (текущий action указанный в ajaxs) и origin_data (неизменные данные указанные в ajaxs). Нужно это, чтобы удобно было идентифицировать ajaxs запрос при обработке JS событий и удобно пожно было получать DOM элементы если они были переданы в данных ajaxs.

= 1.2.6 =
* NEW: parameter jx added to accepted data for JS events `ajaxs_always` and `ajaxs_fail`.
* BUG: wrong data collect for inputs with `text` type if `name` attribute contains an array, ex: `<input type="text" name="key[]">`.


= 1.2.5 =
* NEW: When we get value through magic property, for example '$jx->key', integers converts from string to int, and spaces are removed at beginning and at the end of strings.

= 1.2.4 =
* NEW: If you pass a variable as a Boolean value or a number in JS (AJAX), in PHP you get it as a string: passing false > you got "false". This behavior sometimes makes it very difficult to develop, because sometimes it is not clear why the code does not work: we passed false and checks the variable through if(), but it works, because it is a string "false", which is true when we check it... To get rid of these problems, ajaxs now converts Boolean and numbers values to their real types!
* BUG: Method `$jx->log()` did not fully copy `$jx->console()` - did not understand if you pass several parameters to output to the log.

= 1.2.3 =
* BUG: In the options file was PHP comments which are in the list of plugins appears plug-in Primer...

= 1.2.2 =
* NEW: Added an options page. You can now conveniently enable basic nonce check, include js inline, and define a AJAXS handler file for the front-end.

= 1.2.1 =
* NEW: parameter 'uploadProgress' for ajaxs() second parameter 'data'. It allow to get file upload progress. accepts percentage of uploading file in JS.

= 1.2.0 =
* CHG: data.ajax parameter passing to ajaxs() function now is removed in early state to not become part of common parameters.

= 1.1.9 =
* NEW: For convinience: collect values of multiple file upload array to combined file data - see 'compact' index of multiple files uploaded array.

= 1.1.8 =
* ADD: New methods `$jx->done()` and `$jx->ok()` as alias of `$jx->success()`.
* ADD: `$jx->success()` and `$jx->error()` return object with additional alias properties: error, ok.

= 1.1.6 =
* FIX: `$jx->jseval('return;')` caused an error: `Syntax Error: Illegal return statement`.

= 1.1.5 =
* NEW: `$jx->call( $func_name, $param1, $param2, ... )` - parameters `$param1, $param2, ...` passed to JS function as it specified. `$func_name` can contain prefix `window.` it will be cut later. `$func_name` can be property of another window object: ex. `window.myObject.myFunctionName` or simple `myObject.myFunctionName`.

= 1.1.4 =
* NEW: `$jx->success( $data )`, `$jx->error( $data )` - $data parameter become unrequired.

= 1.1.3 =
* ADD: new method `$jx->log()` = `$jx->console()`.

= 1.1.2 =
* ADD: filter 'ajaxs_allow_process'. Allows set common checks before ajaxs process request parsing.

= 1.1.0 =
* ADD: now you can call class with any name, but set 'ajaxs_' or 'ajaxs_priv_' prefix for method of the class.

= 1.0 =
* IMP: for security now handler class must have 'AJAXS_' prefix. Class access by global variable and function that return instance were removed too.

= 0.950 =
* IMP: now `$jx->console()` and `$jx->dump()` accept multiple parametrs. `$jx->console( 'one', 'two', 'three', ... )`

= 0.948 =
* IMP: AJAX_Simply_Core::files property become magic...

= 0.947 =
* IMP: now plugin catches PHP errors and display it in console in early state (for ex. erros in functions.php). Fatal errors becomes red (console.error).

= 0.946 =
* IMP: 'response' json element not set in the request if json 'extra' part not set - response returns as it is...

= 0.945 =
* NEW: PHP errors (including fatal) now shows in browser console. Only if WP_DEBUG is enabled.

= 0.917 =
* First version


