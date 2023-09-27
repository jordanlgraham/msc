/**
 * @file
 * Custom scripts for theme.
 */

/**
 *  Vanilla JS dom ready function. Can use jQuery ready() instead if theme ends up using jQuery.
 * @param fn
 */
function ready(fn) {
  if (document.readyState != 'loading') {
    fn();
  } else {
    document.addEventListener('DOMContentLoaded', fn);
  }
}

/**
 * Debounce function. Use to debounce any actions attached to window resize event
 * @param func
 * @param wait
 * @param immediate
 * @returns {Function}
 */

function debounce(func, wait, immediate) {
  var timeout;
  return function () {
    var context = this,
        args = arguments;
    var later = function later() {
      timeout = null;
      if (!immediate) {
        func.apply(context, args);
      }
    };
    var callNow = immediate && !timeout;
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
    if (callNow) {
      func.apply(context, args);
    }
  };
}
