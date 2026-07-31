(function ($) {
    'use strict';

    function esc(value) {
        return $('<div>').text(value || '').html();
    }

    function directionUrl(store) {
        var destination = store.address ? store.address : (store.lat + ',' + store.lng);

        return 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(destination);
    }

    function hasCoordinates(store) {
        return store.hasCoordinates && store.lat !== null && store.lng !== null && !Number.isNaN(parseFloat(store.lat)) && !Number.isNaN(parseFloat(store.lng));
    }

    function icon(name) {
        var icons = {
            address: 'fa-solid fa-map-location-dot',
            phone: 'fa-solid fa-phone',
            email: 'fa-solid fa-envelope',
            hours: 'fa-solid fa-door-open',
            direction: 'fa-solid fa-location-arrow'
        };

        return '<i class="lsm-ico lsm-ico-' + name + ' ' + icons[name] + '" aria-hidden="true"></i>';
    }

    function defaultMarkerIcon() {
        return L.divIcon({
            className: 'lsm-marker-pin',
            html: '<span></span>',
            iconSize: [54, 64],
            iconAnchor: [27, 60],
            popupAnchor: [0, -56]
        });
    }

    function makeIcon(url) {
        if (!url) {
            return null;
        }

        return L.icon({
            iconUrl: url,
            iconSize: [42, 52],
            iconAnchor: [21, 52],
            popupAnchor: [0, -48]
        });
    }

    function popupHtml(store, settings) {
        var image = store.image ? '<img src="' + esc(store.image) + '" alt="">' : '';
        var phone = store.phone || store.hotline;
        var link = store.link ? store.link : '#';
        var direction = settings.enableDirection && (store.address || hasCoordinates(store)) ? '<a class="lsm-popup-direction" href="' + directionUrl(store) + '" target="_blank" rel="noopener">' + icon('direction') + 'Chỉ đường</a>' : '';

        return [
            '<div class="lsm-popup">',
            image ? '<div class="lsm-popup-image">' + image + '</div>' : '',
            '<div class="lsm-popup-body">',
            '<h3>' + esc(store.title) + '</h3>',
            store.address ? '<p class="lsm-line-address">' + icon('address') + '<span>' + esc(store.address) + '</span></p>' : '',
            phone ? '<p class="lsm-line-phone">' + icon('phone') + '<span>' + esc(phone) + '</span></p>' : '',
            store.email ? '<p class="lsm-line-email">' + icon('email') + '<span>' + esc(store.email) + '</span></p>' : '',
            store.openHours ? '<p class="lsm-line-hours">' + icon('hours') + '<span>' + esc(store.openHours) + '</span></p>' : '',
            '<div class="lsm-popup-actions">',
            '<a class="lsm-more" href="' + esc(link) + '">Xem thêm</a>',
            direction,
            '</div>',
            '</div>',
            '</div>'
        ].join('');
    }

    function listItemHtml(store, settings) {
        var phone = store.phone || store.hotline;
        var image = store.image ? '<img src="' + esc(store.image) + '" alt="">' : '<div class="lsm-no-image"></div>';

        return [
            '<article class="lsm-store-card" role="listitem" data-id="' + store.id + '">',
            '<div class="lsm-card-image">' + image + '</div>',
            '<div class="lsm-card-body">',
            '<h3>' + esc(store.title) + '</h3>',
            store.address ? '<p class="lsm-line-address">' + icon('address') + '<span>' + esc(store.address) + '</span></p>' : '',
            phone ? '<p class="lsm-line-phone">' + icon('phone') + '<span>' + esc(phone) + '</span></p>' : '',
            store.email ? '<p class="lsm-optional lsm-line-email">' + icon('email') + '<span>' + esc(store.email) + '</span></p>' : '',
            store.openHours ? '<p class="lsm-optional lsm-line-hours">' + icon('hours') + '<span>' + esc(store.openHours) + '</span></p>' : '',
            '</div>',
            '</article>'
        ].join('');
    }

    function initLocator(root) {
        var configName = root.data('config');
        var config = window[configName];

        if (!config || typeof L === 'undefined') {
            return;
        }

        var settings = config.settings || {};
        var mapEl = root.find('.lsm-map').get(0);
        var listEl = root.find('.lsm-list');
        var countNumberEl = root.find('.lsm-result-number');
        var keywordEl = root.find('.lsm-keyword');
        var provinceEl = root.find('.lsm-province');
        var districtEl = root.find('.lsm-district');
        var stores = config.stores || [];
        var markers = {};
        var markerLayer = L.layerGroup();
        var defaultIcon = makeIcon(settings.markerIcon) || defaultMarkerIcon();

        var map = L.map(mapEl).setView(
            [
                settings.defaultLat || 21.0277644,
                settings.defaultLng || 105.8341598
            ],
            settings.zoom || 13
        );

        L.tileLayer(settings.tileUrl || 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            maxZoom: 19,
            attribution: settings.tileAttribution || '&copy; OpenStreetMap &copy; CARTO'
        }).addTo(map);

        markerLayer.addTo(map);

        function makeStoreTermIndex() {
            var index = {
                provinces: {},
                districts: {}
            };

            (config.regions || []).forEach(function (region) {
                var provinceHasStores = false;
                var districtIds = {};

                stores.forEach(function (store) {
                    var storeTerms = store.terms || [];
                    var storeParents = store.parents || [];

                    if (storeTerms.indexOf(region.id) !== -1 || storeParents.indexOf(region.id) !== -1) {
                        provinceHasStores = true;
                    }

                    (region.children || []).forEach(function (child) {
                        if (storeTerms.indexOf(child.id) !== -1) {
                            districtIds[child.id] = true;
                            provinceHasStores = true;
                        }
                    });
                });

                if (provinceHasStores) {
                    index.provinces[region.id] = true;
                    index.districts[region.id] = districtIds;
                }
            });

            return index;
        }

        var storeTermIndex = makeStoreTermIndex();

        function renderProvinces() {
            var selectedProvince = parseInt(provinceEl.val() || '0', 10);
            var html = '<option value="">Toàn Quốc</option>';

            (config.regions || []).forEach(function (region) {
                if (!storeTermIndex.provinces[region.id]) {
                    return;
                }

                html += '<option value="' + region.id + '">' + esc(region.name) + '</option>';
            });

            provinceEl.html(html);

            if (selectedProvince && storeTermIndex.provinces[selectedProvince]) {
                provinceEl.val(String(selectedProvince));
            }
        }

        function storeMatches(store) {
            var keyword = keywordEl.val().toLowerCase().trim();
            var province = parseInt(provinceEl.val() || '0', 10);
            var district = parseInt(districtEl.val() || '0', 10);
            var haystack = [
                store.title,
                store.address,
                store.phone,
                store.hotline,
                store.email,
                store.openHours
            ].join(' ').toLowerCase();

            if (keyword && haystack.indexOf(keyword) === -1) {
                return false;
            }

            if (district && store.terms.indexOf(district) === -1) {
                return false;
            }

            if (province && store.parents.indexOf(province) === -1 && store.terms.indexOf(province) === -1) {
                return false;
            }

            return true;
        }

        function renderDistricts() {
            var province = parseInt(provinceEl.val() || '0', 10);
            var selectedDistrict = parseInt(districtEl.val() || '0', 10);
            var selectedRegion = (config.regions || []).find(function (region) {
                return region.id === province;
            });
            var availableDistricts = storeTermIndex.districts[province] || {};

            var html = '<option value="">Quận/Huyện</option>';

            if (selectedRegion) {
                selectedRegion.children.forEach(function (child) {
                    if (!availableDistricts[child.id]) {
                        return;
                    }

                    html += '<option value="' + child.id + '">' + esc(child.name) + '</option>';
                });
            }

            districtEl.html(html);

            if (selectedDistrict && availableDistricts[selectedDistrict]) {
                districtEl.val(String(selectedDistrict));
            }
        }

        function fitMarkers(visibleStores) {
            var storesWithCoordinates = visibleStores.filter(hasCoordinates);

            if (!storesWithCoordinates.length) {
                map.setView(
                    [
                        settings.defaultLat || 21.0277644,
                        settings.defaultLng || 105.8341598
                    ],
                    settings.zoom || 13
                );
                return;
            }

            var bounds = L.latLngBounds(storesWithCoordinates.map(function (store) {
                return [store.lat, store.lng];
            }));

            map.fitBounds(bounds, {
                padding: [40, 40],
                maxZoom: Math.max(settings.zoom || 13, 15)
            });
        }

        function render() {
            var visibleStores = stores.filter(storeMatches);

            markerLayer.clearLayers();
            markers = {};
            listEl.empty();
            countNumberEl.text(visibleStores.length);
            listEl.toggleClass('is-empty', !visibleStores.length);

            if (!visibleStores.length) {
                listEl.html('<div class="lsm-empty">Không tìm thấy cửa hàng phù hợp.</div>');
                fitMarkers(visibleStores);
                return;
            }

            visibleStores.forEach(function (store) {
                if (!hasCoordinates(store)) {
                    listEl.append(listItemHtml(store, settings));
                    return;
                }

                var markerIcon = makeIcon(store.markerIcon) || defaultIcon;
                var markerOptions = markerIcon ? { icon: markerIcon } : {};
                var marker = L.marker([store.lat, store.lng], markerOptions).bindPopup(popupHtml(store, settings));

                markers[store.id] = marker;
                markerLayer.addLayer(marker);
                listEl.append(listItemHtml(store, settings));
            });

            fitMarkers(visibleStores);
        }

        listEl.on('click', '.lsm-store-card', function (event) {
            if ($(event.target).closest('a').length) {
                return;
            }

            var id = parseInt($(this).data('id'), 10);

            if (markers[id]) {
                map.setView(markers[id].getLatLng(), Math.max(map.getZoom(), 15));
                markers[id].openPopup();

                root.find('.lsm-store-card').removeClass('is-active');
                $(this).addClass('is-active');
            }
        });

        provinceEl.on('change', function () {
            districtEl.val('');
            renderDistricts();
            render();
        });

        districtEl.on('change', render);
        keywordEl.on('input', render);
        root.find('.lsm-search-button').on('click', render);

        root.find('.lsm-toggle-panel').on('click', function () {
            root.toggleClass('is-panel-collapsed');

            setTimeout(function () {
                map.invalidateSize(true);
            }, 100);

            setTimeout(function () {
                map.invalidateSize(true);
            }, 350);
        });

        renderProvinces();
        renderDistricts();
        render();

        setTimeout(function () {
            map.invalidateSize();
        }, 300);

        if (typeof ResizeObserver !== 'undefined') {
            var resizeObserver = new ResizeObserver(function () {
                map.invalidateSize();
            });

            resizeObserver.observe(root.get(0));
        }
    }

    $(function () {
        $('.lsm-store-locator').each(function () {
            initLocator($(this));
        });
    });
})(jQuery);
