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
