// ==================================================
// Project Name  :  Appal
// File          :  JS Base
// Version       :  1.0.0
// Last change   :  23 February 2019
// Author        :  Marvel Theme
// Developer:    :  Rakibul Islam Dewan
// ==================================================

(function($) {
  "use strict";

  $(document).ready(function () {
    $('.close-btn, .overlay').on('click', function () {
      $('#sidebar-menu').removeClass('active');
      $('.overlay').removeClass('active');
    });

    $('#sidebar-collapse').on('click', function () {
      $('#sidebar-menu').addClass('active');
      $('.overlay').addClass('active');
    });
  });

  // back to top - start
  // --------------------------------------------------
  $(window).scroll(function() {
    if ($(this).scrollTop() > 200) {
      $('#backtotop:hidden').stop(true, true).fadeIn();
    } else {
      $('#backtotop').stop(true, true).fadeOut();
    }
  });
  $(function() {
    $("#scroll").on('click', function() {
      $("html,body").animate({
        scrollTop: $("#thetop").offset().top
      }, "slow");
      return false
    })
  });
  // back to top - end
  // --------------------------------------------------

  // preloader - start
  // --------------------------------------------------
  /*$(window).on('load', function(){
    $('#preloader').fadeOut('slow',function(){
      $(this).remove();
    });
  });*/
  // preloader - end
  // --------------------------------------------------

  // main search - start
  // --------------------------------------------------
  $('.search-btn').on('click', function() {
    $('.main-search-body').toggleClass('search-open');
  });
  // main search - end
  // --------------------------------------------------

  // multy count down - start
  // --------------------------------------------------
  $('.countdown-list').each(function(){
    $('[data-countdown]').each(function() {
      var $this = $(this), finalDate = $(this).data('countdown');
      $this.countdown(finalDate, function(event) {
        var $this = $(this).html(event.strftime(''
          + '<li class="timer-item days"><strong>%D</strong><small>days</small></li>'
          + '<li class="timer-item hours"><strong>%H</strong><small>hours</small></li>'
          + '<li class="timer-item mins"><strong>%M</strong><small>mins</small></li>'
          + '<li class="timer-item sec"><strong>%S</strong><small>sec</small></li>'));
      });
    });

  });
  // multy count down - end
  // --------------------------------------------------

  // popup-video - start
  // --------------------------------------------------
  $('.popup-video').magnificPopup({
    disableOn: 700,
    type: 'iframe',
    preloader: false,
    removalDelay: 160,
    mainClass: 'mfp-fade',
    fixedContentPos: false
  });
  // popup-video - end
  // --------------------------------------------------

  // mobileapp-main-carousel - end
  // --------------------------------------------------
  $('#mobileapp-main-carousel').owlCarousel({
    items:1,
    margin:0,
    nav:false,
    loop:true,
    autoplay:true,
    dots:true,
    autoHeight:true,
    // autoplay:true,
    smartSpeed:1000,
    // animateIn: 'fadeIn',
    autoplayTimeout:5000
  });
  // mobileapp-main-carousel - end
  // --------------------------------------------------

  // appstore-main-carousel - end
  // --------------------------------------------------
  $('#appstore-main-carousel').owlCarousel({
    items:1,
    margin:0,
    nav:true,
    loop:true,
    dots:false,
    autoplay:true,
    smartSpeed:1000,
    // animateIn: 'fadeIn',
    autoplayTimeout:6000
  });
  // appstore-main-carousel - end
  // --------------------------------------------------

  // client-carousel - start
  // --------------------------------------------------
  $('#client-carousel').owlCarousel({
    loop:true,
    margin:30,
    dots:false,
    autoplay:true,
    smartSpeed:1000,
    autoplayTimeout:3500,
    autoplayHoverPause:true,
    responsive:{
      0:{
        items:1,
        nav:true
      },
      600:{
        items:2,
        nav:true
      },
      768:{
        items:3,
        nav:true
      },
      1000:{
        items:5,
        nav:false
      },
      1920:{
        items:5,
        nav:false
      }
    }
  });
  // client-carousel - end
  // --------------------------------------------------

  // service-carousel - start
  // --------------------------------------------------
  $('#service-carousel').owlCarousel({
    loop:true,
    margin:30,
    merge:true,
    // center: true,
    autoplay:true,
    smartSpeed:1000,
    autoplayTimeout:4000,
    autoplayHoverPause:true,
    responsive:{
      0:{
        items:1,
        nav:true,
        dots: false
      },
      600:{
        items:3,
        nav:true,
        dots: false
      },
      1000:{
        items:4,
        nav:false,
        dots:true
      },
      1200:{
        items:4,
        nav:false,
        dots:true
      },
      1920:{
        items:5,
        nav:false,
        dots:true
      }
    }
  });
  // service-carousel - end
  // --------------------------------------------------

  // testimonial-carousel - start
  // --------------------------------------------------
  $('#testimonial-carousel').owlCarousel({
    items:1,
    nav:true,
    loop:true,
    margin:80,
    dots:false,
    merge:true,
    center: true,
    autoplay:true,
    smartSpeed:1000,
    autoplayTimeout:5000,
    autoplayHoverPause:true
  });
  // testimonial-carousel - end
  // --------------------------------------------------

  // blog-carousel - start
  // --------------------------------------------------
  $('#blog-carousel').owlCarousel({
    nav:true,
    loop:true,
    margin:30,
    autoplay:true,
    smartSpeed:1000,
    autoplayTimeout:5000,
    autoplayHoverPause:true,
    responsive:{
      0:{
        items:1,
        dots:false
      },
      600:{
        items:2,
        dots:false
      },
      1000:{
        items:3,
        nav:false
      },
      1920:{
        items:3,
        nav:false
      }
    }
  });
  // blog-carousel - end
  // --------------------------------------------------

  // Counter - start
  // --------------------------------------------------
  $('.count-text').counterUp({
    delay: 10,
    time: 1000
  });
  // Counter - end
  // --------------------------------------------------

  // sticky menu - start
  // --------------------------------------------------
  var headerId = $(".sticky-header");
  $(window).on('scroll' , function() {
    var amountScrolled = $(window).scrollTop();
    if ($(this).scrollTop() > 50) {
      headerId.removeClass("not-stuck");
      headerId.addClass("stuck");
    } else {
      headerId.removeClass("stuck");
      headerId.addClass("not-stuck");
    }
  });
  // sticky menu - end
  // --------------------------------------------------

  // scroll animation - start
  // --------------------------------------------------
  AOS.init({
    disable: function() {
      var maxWidth = 480;
      return window.innerWidth < maxWidth;
    }
  });
  // scroll animation - end
  // --------------------------------------------------

  // google map - start
  // --------------------------------------------------
  if ($('#gmap').length > 0) {
    new GMaps({
      div: '#gmap',
      lat: 23.7947172,
      lng: 90.3971412,
      scrollwheel: false,       
      styles: [
      {
        "featureType": "landscape",
        "elementType": "geometry",
        "stylers": [
        {
          "color": "#dddddd"
        },
        {
          "lightness": 20
        }
        ]
      },
      {
        "featureType": "road.highway",
        "elementType": "geometry.fill",
        "stylers": [
        {
          "color": "#ffffff"
        },
        {
          "lightness": 17
        }
        ]
      },
      {
        "featureType": "road.highway",
        "elementType": "geometry.stroke",
        "stylers": [
        {
          "color": "#ffffff"
        },
        {
          "lightness": 29
        },
        {
          "weight": 0.2
        }
        ]
      },
      {
        "featureType": "road.arterial",
        "elementType": "geometry",
        "stylers": [
        {
          "color": "#ffffff"
        },
        {
          "lightness": 18
        }
        ]
      },
      {
        "featureType": "road.local",
        "elementType": "geometry",
        "stylers": [
        {
          "color": "#dddddd"
        },
        {
          "lightness": 16
        }
        ]
      },
      {
        "featureType": "poi",
        "elementType": "geometry",
        "stylers": [
        {
          "color": "#ffffff"
        },
        {
          "lightness": 21
        }
        ]
      },
      {
        "featureType": "poi.park",
        "elementType": "geometry",
        "stylers": [
        {
          "color": "#ffffff"
        },
        {
          "lightness": 21
        }
        ]
      },
      {
        "elementType": "labels.text.stroke",
        "stylers": [
        {
          "visibility": "on"
        },
        {
          "color": "#ffffff"
        },
        {
          "lightness": 16
        }
        ]
      },
      {
        "elementType": "labels.icon",
        "stylers": [
        {
          "visibility": "on"
        }
        ]
      }
      ]
    }).addMarker({
      lat: 23.792930,
      lng: 90.403798,
      options: { icon: 'assets/images/map-mark.png' }
      // infoWindow: {
      //   content: '<p> loaction</p>'
      // }
    });

  }
  // google map - end
  // --------------------------------------------------

  // parallax - start
  // --------------------------------------------------
  var scene = $('.scene').get(0);
  var parallaxInstance = new Parallax(scene);
  // parallax - end
  // --------------------------------------------------


  
})(jQuery);