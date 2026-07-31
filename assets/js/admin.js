(function ($) {
    'use strict';

    function initTabs() {
        $('.lsm-settings .nav-tab').on('click', function (event) {
            event.preventDefault();
            var target = $(this).attr('href');
            $('.lsm-settings .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.lsm-tab-panel').removeClass('is-active');
            $(target).addClass('is-active');
        });
    }

    function initMediaButtons() {
        $('.lsm-media-button').on('click', function (event) {
            event.preventDefault();
            var button = $(this);
            var target = $(button.data('target'));
            var frame = wp.media({
                title: 'Chọn icon',
                button: { text: 'Sử dụng' },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                target.val(attachment.url).trigger('change');
                button.siblings('.lsm-image-preview').html('<img src="' + attachment.url + '" alt="">');
            });

            frame.open();
        });

        $('.lsm-media-clear').on('click', function (event) {
            event.preventDefault();
            var target = $($(this).data('target'));
            target.val('').trigger('change');
            $(this).siblings('.lsm-image-preview').empty();
        });
    }

    function initAdminMap() {
        var mapEl = document.getElementById('lsm-admin-map');
        if (!mapEl || typeof L === 'undefined') {
            return;
        }

        var wrap = mapEl.closest('.lsm-admin-grid');
        var latInput = document.getElementById('localstore_maps');
        var lngInput = document.getElementById('localstore_maps_lng');
        var lat = parseFloat(latInput.value || wrap.dataset.defaultLat || 21.0277644);
        var lng = parseFloat(lngInput.value || wrap.dataset.defaultLng || 105.8341598);
        var zoom = parseInt(wrap.dataset.defaultZoom || '13', 10);

        var map = L.map(mapEl).setView([lat, lng], zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        var marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        function setPosition(position) {
            latInput.value = Number(position.lat).toFixed(7);
            lngInput.value = Number(position.lng).toFixed(7);
            marker.setLatLng(position);
        }

        marker.on('dragend', function () {
            setPosition(marker.getLatLng());
        });

        map.on('click', function (event) {
            setPosition(event.latlng);
        });

        $(latInput).add(lngInput).on('change', function () {
            var nextLat = parseFloat(latInput.value);
            var nextLng = parseFloat(lngInput.value);
            if (!Number.isNaN(nextLat) && !Number.isNaN(nextLng)) {
                var next = L.latLng(nextLat, nextLng);
                marker.setLatLng(next);
                map.setView(next);
            }
        });

        setTimeout(function () {
            map.invalidateSize();
        }, 300);
    }

    $(function () {
        initTabs();
        initMediaButtons();
        initAdminMap();
        $('.lsm-color-field').wpColorPicker();
    });
})(jQuery);
