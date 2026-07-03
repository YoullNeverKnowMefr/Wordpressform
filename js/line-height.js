tinymce.PluginManager.add('lineheight', function(editor) {
    // Список значений интервала
    var lineHeights = [
        { text: '1.0', value: '1' },
        { text: '1.2', value: '1.2' },
        { text: '1.3', value: '1.3' },
        { text: '1.4', value: '1.4' },
        { text: '1.5', value: '1.5' },
        { text: '2.0', value: '2' }
    ];

    // Создаем меню
    var menuItems = [];
    lineHeights.forEach(function(item) {
        menuItems.push({
            text: item.text,
            onclick: function() {
                editor.formatter.apply('lineheight' + item.value.replace('.', ''));
            }
        });
    });

    // Добавляем кнопку
    editor.addButton('lineheight', {
        title: 'Межстрочный интервал',
        type: 'menubutton',
        text: 'LH', // Стандартная иконка WordPress
        menu: menuItems
    });

    // Регистрируем стили
    editor.on('init', function() {
        lineHeights.forEach(function(item) {
            var formatName = 'lineheight' + item.value.replace('.', '');
            editor.formatter.register(formatName, {
                inline: 'span',
                styles: { 'line-height': item.value },
                attributes: { 'data-line-height': item.value }
            });
        });
    });
});