jQuery(document).ready(function($) {
    $('#upload_chat_icon_button').click(function(e) {
        e.preventDefault();
        var image = wp.media({
            title: 'Upload Chat Icon',
            multiple: false
        }).open()
        .on('select', function(e){
            var uploaded_image = image.state().get('selection').first();
            var image_url = uploaded_image.toJSON().url;
            $('#knowie_wp_chat_icon').val(image_url);
            $('#knowie_wp_chat_icon').siblings('img').attr('src', image_url);
        });
    });
});