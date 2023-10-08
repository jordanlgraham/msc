ready(function () {

  // Add active class to product tabs when clicked
  var productTabs = document.querySelectorAll('.product-tabs a');
  if (productTabs != null) {
    for (var _iterator = productTabs, _isArray = Array.isArray(_iterator), _i = 0, _iterator = _isArray ? _iterator : _iterator[Symbol.iterator]();;) {
      var _ref;

      if (_isArray) {
        if (_i >= _iterator.length) break;
        _ref = _iterator[_i++];
      } else {
        _i = _iterator.next();
        if (_i.done) break;
        _ref = _i.value;
      }

      var productTab = _ref;

      productTab.addEventListener('click', function (e) {

        var activeTabs = document.querySelectorAll('.product-tabs a.active');

        for (var _iterator2 = activeTabs, _isArray2 = Array.isArray(_iterator2), _i2 = 0, _iterator2 = _isArray2 ? _iterator2 : _iterator2[Symbol.iterator]();;) {
          var _ref2;

          if (_isArray2) {
            if (_i2 >= _iterator2.length) break;
            _ref2 = _iterator2[_i2++];
          } else {
            _i2 = _iterator2.next();
            if (_i2.done) break;
            _ref2 = _i2.value;
          }

          var activeTab = _ref2;

          if (activeTab !== e.target) {
            activeTab.classList.remove('active');
          }
        }

        e.target.classList.add('active');
      });
    }
  }

  smoothScroll.init({
    // Selectors
    selector: '.product-tabs a, .arrow-section a', // Selector for links (must be a valid CSS selector)
    selectorHeader: '.header', // Selector for fixed headers (must be a valid CSS selector) [optional]

    // Speed & Easing
    speed: 500, // Integer. How fast to complete the scroll in milliseconds
    offset: 0, // Integer or Function returning an integer. How far to offset the scrolling anchor location in pixels
    easing: 'easeInOutCubic' });
});
