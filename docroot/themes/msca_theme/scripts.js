/**
 * Constants used throughout javascript
 */

var mscaConstants = {
  // media query constants to make it easier to figure out in js what breakpoint we're at.
  // These same values are defined in less variables and assigned in css (see themes/custom/biodex/sass/layout/layout.scss)
  MEDIA_QUERY_BASE: '1px',
  MEDIA_QUERY_MIN_WIDTH_SM: '2px',
  MEDIA_QUERY_MIN_WIDTH_MD: '3px',
  MEDIA_QUERY_MIN_WIDTH_LG: '4px'
};

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

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

/**
 * --------------------------------------------------------------------------
 * Bootstrap (v4.0.0-alpha.6): tab.js
 * Licensed under MIT (https://github.com/twbs/bootstrap/blob/master/LICENSE)
 * --------------------------------------------------------------------------
 */

var Tab = function ($) {

  /**
   * ------------------------------------------------------------------------
   * Constants
   * ------------------------------------------------------------------------
   */

  var NAME = 'tab';
  var VERSION = '4.0.0-alpha.6';
  var DATA_KEY = 'bs.tab';
  var EVENT_KEY = '.' + DATA_KEY;
  var DATA_API_KEY = '.data-api';
  var JQUERY_NO_CONFLICT = $.fn[NAME];
  var TRANSITION_DURATION = 150;

  var Event = {
    HIDE: 'hide' + EVENT_KEY,
    HIDDEN: 'hidden' + EVENT_KEY,
    SHOW: 'show' + EVENT_KEY,
    SHOWN: 'shown' + EVENT_KEY,
    CLICK_DATA_API: 'click' + EVENT_KEY + DATA_API_KEY
  };

  var ClassName = {
    DROPDOWN_MENU: 'dropdown-menu',
    ACTIVE: 'active',
    DISABLED: 'disabled',
    FADE: 'fade',
    SHOW: 'show'
  };

  var Selector = {
    A: 'a',
    LI: 'li',
    DROPDOWN: '.dropdown',
    LIST: 'ul:not(.dropdown-menu), ol:not(.dropdown-menu), nav:not(.dropdown-menu)',
    FADE_CHILD: '> .nav-item .fade, > .fade',
    ACTIVE: '.active',
    ACTIVE_CHILD: '> .nav-item > .active, > .active',
    DATA_TOGGLE: '[data-toggle="tab"], [data-toggle="pill"]',
    DROPDOWN_TOGGLE: '.dropdown-toggle',
    DROPDOWN_ACTIVE_CHILD: '> .dropdown-menu .active'

    /**
     * ------------------------------------------------------------------------
     * Class Definition
     * ------------------------------------------------------------------------
     */

  };
  var Tab = function () {
    function Tab(element) {
      _classCallCheck(this, Tab);

      this._element = element;
    }

    // getters

    // public

    Tab.prototype.show = function show() {
      var _this = this;

      if (this._element.parentNode && this._element.parentNode.nodeType === Node.ELEMENT_NODE && $(this._element).hasClass(ClassName.ACTIVE) || $(this._element).hasClass(ClassName.DISABLED)) {
        return;
      }

      var target = void 0;
      var previous = void 0;
      var listElement = $(this._element).closest(Selector.LIST)[0];
      var selector = Util.getSelectorFromElement(this._element);

      if (listElement) {
        previous = $.makeArray($(listElement).find(Selector.ACTIVE));
        previous = previous[previous.length - 1];
      }

      var hideEvent = $.Event(Event.HIDE, {
        relatedTarget: this._element
      });

      var showEvent = $.Event(Event.SHOW, {
        relatedTarget: previous
      });

      if (previous) {
        $(previous).trigger(hideEvent);
      }

      $(this._element).trigger(showEvent);

      if (showEvent.isDefaultPrevented() || hideEvent.isDefaultPrevented()) {
        return;
      }

      if (selector) {
        target = $(selector)[0];
      }

      this._activate(this._element, listElement);

      var complete = function complete() {
        var hiddenEvent = $.Event(Event.HIDDEN, {
          relatedTarget: _this._element
        });

        var shownEvent = $.Event(Event.SHOWN, {
          relatedTarget: previous
        });

        $(previous).trigger(hiddenEvent);
        $(_this._element).trigger(shownEvent);
      };

      if (target) {
        this._activate(target, target.parentNode, complete);
      } else {
        complete();
      }
    };

    Tab.prototype.dispose = function dispose() {
      $.removeClass(this._element, DATA_KEY);
      this._element = null;
    };

    // private

    Tab.prototype._activate = function _activate(element, container, callback) {
      var _this2 = this;

      var active = $(container).find(Selector.ACTIVE_CHILD)[0];
      var isTransitioning = callback && Util.supportsTransitionEnd() && (active && $(active).hasClass(ClassName.FADE) || Boolean($(container).find(Selector.FADE_CHILD)[0]));

      var complete = function complete() {
        return _this2._transitionComplete(element, active, isTransitioning, callback);
      };

      if (active && isTransitioning) {
        $(active).one(Util.TRANSITION_END, complete).emulateTransitionEnd(TRANSITION_DURATION);
      } else {
        complete();
      }

      if (active) {
        $(active).removeClass(ClassName.SHOW);
      }
    };

    Tab.prototype._transitionComplete = function _transitionComplete(element, active, isTransitioning, callback) {
      if (active) {
        $(active).removeClass(ClassName.ACTIVE);

        var dropdownChild = $(active.parentNode).find(Selector.DROPDOWN_ACTIVE_CHILD)[0];

        if (dropdownChild) {
          $(dropdownChild).removeClass(ClassName.ACTIVE);
        }

        active.setAttribute('aria-expanded', false);
      }

      $(element).addClass(ClassName.ACTIVE);
      element.setAttribute('aria-expanded', true);

      if (isTransitioning) {
        Util.reflow(element);
        $(element).addClass(ClassName.SHOW);
      } else {
        $(element).removeClass(ClassName.FADE);
      }

      if (element.parentNode && $(element.parentNode).hasClass(ClassName.DROPDOWN_MENU)) {

        var dropdownElement = $(element).closest(Selector.DROPDOWN)[0];
        if (dropdownElement) {
          $(dropdownElement).find(Selector.DROPDOWN_TOGGLE).addClass(ClassName.ACTIVE);
        }

        element.setAttribute('aria-expanded', true);
      }

      if (callback) {
        callback();
      }
    };

    // static

    Tab._jQueryInterface = function _jQueryInterface(config) {
      return this.each(function () {
        var $this = $(this);
        var data = $this.data(DATA_KEY);

        if (!data) {
          data = new Tab(this);
          $this.data(DATA_KEY, data);
        }

        if (typeof config === 'string') {
          if (data[config] === undefined) {
            throw new Error('No method named "' + config + '"');
          }
          data[config]();
        }
      });
    };

    _createClass(Tab, null, [{
      key: 'VERSION',
      get: function get() {
        return VERSION;
      }
    }]);

    return Tab;
  }();

  /**
   * ------------------------------------------------------------------------
   * Data Api implementation
   * ------------------------------------------------------------------------
   */

  $(document).on(Event.CLICK_DATA_API, Selector.DATA_TOGGLE, function (event) {
    event.preventDefault();
    Tab._jQueryInterface.call($(this), 'show');
  });

  /**
   * ------------------------------------------------------------------------
   * jQuery
   * ------------------------------------------------------------------------
   */

  $.fn[NAME] = Tab._jQueryInterface;
  $.fn[NAME].Constructor = Tab;
  $.fn[NAME].noConflict = function () {
    $.fn[NAME] = JQUERY_NO_CONFLICT;
    return Tab._jQueryInterface;
  };

  return Tab;
}(jQuery);

var _typeof = typeof Symbol === "function" && typeof Symbol.iterator === "symbol" ? function (obj) { return typeof obj; } : function (obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; };

/**
 * stickybits - Stickybits is a lightweight (~2KB) alternative to `position: sticky` polyfills
 * @version v1.3.7
 * @link https://github.com/dollarshaveclub/stickybits#readme
 * @author Jeff Wainwright <jjwainwright2@gmail.com>
 * @license MIT */
!function (t, s) {
  "object" == (typeof exports === "undefined" ? "undefined" : _typeof(exports)) && "undefined" != typeof module ? module.exports = s() : "function" == typeof define && define.amd ? define(s) : t.stickybits = s();
}(this, function () {
  "use strict";
  function t(t, s) {
    if ("undefined" == typeof window) throw Error("stickybits requires `window`");return this.el = t, this.offset = s && s.stickyBitStickyOffset || 0, this.vp = s && s.verticalPosition || "top", this.useClasses = s && s.useStickyClasses || !1, this.styles = this.el.style, this.positionVal = "fixed", this.setStickyPosition(), "fixed" !== this.positionVal && !0 !== this.useClasses || this.manageStickiness(), this;
  }function s(t) {
    var s = this;this.privateInstances = t || [], this.cleanup = function () {
      return s.privateInstances.forEach(function (t) {
        return t.cleanup();
      });
    };
  }return t.prototype.setStickyPosition = function () {
    for (var t = ["", "-o-", "-webkit-", "-moz-", "-ms-"], s = this.styles, i = this.vp, e = 0; e < t.length; e += 1) {
      s.position = t[e] + "sticky";
    }return "" !== s.position && (this.positionVal = s.position, "top" === i && (s[i] = this.offset + "px")), this;
  }, t.prototype.manageStickiness = function () {
    var t = this.el,
        s = t.parentNode,
        i = this.positionVal,
        e = this.vp,
        n = this.offset,
        o = this.styles,
        a = t.classList,
        r = window,
        c = r.requestAnimationFrame;s.classList.add("js-stickybit-parent");var f = s.getBoundingClientRect().top,
        u = f + s.offsetHeight - (t.offsetHeight - n),
        p = "default";return this.manageState = function () {
      var t = r.scrollY || r.pageYOffset,
          s = t > f && t < u && ("default" === p || "stuck" === p),
          l = t < f && "sticky" === p,
          h = t > u && "sticky" === p;s ? (p = "sticky", c(function () {
        a.add("js-is-sticky"), a.contains("js-is-stuck") && a.remove("js-is-stuck"), o.bottom = "", o.position = i, o[e] = n + "px";
      })) : l ? (p = "default", c(function () {
        a.remove("js-is-sticky"), "fixed" === i && (o.position = "");
      })) : h && (p = "stuck", c(function () {
        a.remove("js-is-sticky"), a.add("js-is-stuck"), "fixed" === i && (o.top = "", o.bottom = "0", o.position = "absolute");
      }));
    }, r.addEventListener("scroll", this.manageState), this;
  }, t.prototype.cleanup = function () {
    var t = this.el,
        s = this.styles;s.position = "", s[this.vp] = "", t.classList.remove("js-is-sticky", "js-is-stuck"), t.parentNode.classList.remove("js-stickybit-parent"), window.removeEventListener("scroll", this.manageState), this.manageState = !1;
  }, function (i, e) {
    var n = "string" == typeof i ? document.querySelectorAll(i) : i;"length" in n || (n = [n]);for (var o = [], a = 0; a < n.length; a += 1) {
      var r = n[a];o.push(new t(r, e));
    }return new s(o);
  };
});

// Custom scripts go here