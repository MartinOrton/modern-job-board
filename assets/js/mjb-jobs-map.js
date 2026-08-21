/**
 * Google Maps multi-marker map for [mjb_map] / [mjb_jobs_map].
 */
(function (window) {
  'use strict';

  var pending = [];
  var mapsReady = false;

  function parsePayload(el) {
    var raw = el.getAttribute('data-mjb-jobs-map');
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function initMap(el) {
    if (!window.google || !window.google.maps) return;
    var data = parsePayload(el);
    if (!data || !data.markers || !data.markers.length) return;

    var map = new google.maps.Map(el, {
      zoom: data.zoom || 5,
      center: { lat: 0, lng: 0 },
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: true,
    });

    var bounds = new google.maps.LatLngBounds();
    var geocoder = new google.maps.Geocoder();
    var info = new google.maps.InfoWindow();
    var pendingGeo = data.markers.length;
    var hasPoint = false;

    data.markers.forEach(function (markerData) {
      geocoder.geocode({ address: markerData.location }, function (results, status) {
        pendingGeo -= 1;
        if (status === 'OK' && results && results[0]) {
          var loc = results[0].geometry.location;
          hasPoint = true;
          bounds.extend(loc);
          var marker = new google.maps.Marker({
            map: map,
            position: loc,
            title: markerData.title || '',
          });
          marker.addListener('click', function () {
            var company = markerData.company
              ? '<div class="mjb-map-iw__company">' + escapeHtml(markerData.company) + '</div>'
              : '';
            var link = markerData.url
              ? '<a href="' + escapeAttr(markerData.url) + '">' + escapeHtml(markerData.title || 'View job') + '</a>'
              : escapeHtml(markerData.title || '');
            info.setContent(
              '<div class="mjb-map-iw">' +
                '<div class="mjb-map-iw__title">' +
                link +
                '</div>' +
                company +
                '<div class="mjb-map-iw__loc">' +
                escapeHtml(markerData.location || '') +
                '</div></div>'
            );
            info.open(map, marker);
          });
        }
        if (pendingGeo <= 0 && hasPoint) {
          if (data.markers.length === 1) {
            map.setCenter(bounds.getCenter());
            map.setZoom(data.zoom || 10);
          } else {
            map.fitBounds(bounds);
          }
        }
      });
    });
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function escapeAttr(str) {
    return escapeHtml(str).replace(/'/g, '&#39;');
  }

  function scan() {
    var nodes = document.querySelectorAll('[data-mjb-jobs-map]');
    for (var i = 0; i < nodes.length; i++) {
      if (nodes[i].getAttribute('data-mjb-map-ready')) continue;
      nodes[i].setAttribute('data-mjb-map-ready', '1');
      if (mapsReady) {
        initMap(nodes[i]);
      } else {
        pending.push(nodes[i]);
      }
    }
  }

  window.mjbInitJobsMaps = function () {
    mapsReady = true;
    pending.forEach(initMap);
    pending = [];
    scan();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scan);
  } else {
    scan();
  }
})(window);
