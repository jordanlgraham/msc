(function ($, Drupal) {
  Drupal.behaviors.msca_menu = {
    attach: function attach(context, settings) {

      // Mainmenu object
      var mainMenu = {
        topUl: document.getElementById('js-top-menu'),
        subMenus: document.getElementsByClassName('sub-menu'),
        mobileToggler: document.getElementById('js-mobile-toggle'),
        headerWrapper: document.getElementById('js-header-menu'),
        searchBtn: document.getElementById('js-search-btn'),
        searchForm: document.getElementById('js-page-search'),
        searchCloseBtn: document.getElementById('js-search-close-btn'),

        /**
         * Gets breakpoint from CSS to see if screen size is small or large
         *
         * @returns {*}
         */
        getBreakpoint: function getBreakpoint() {
          var breakpoint = window.getComputedStyle(document.body, null).getPropertyValue("min-width");
          if (breakpoint == '1px' || breakpoint == '2px') {
            return 'small';
          } else {
            return 'large';
          }
        },

        /**
         * Toggle search function
         */
        toggleSearch: function toggleSearch() {

          // Close main menu whenever search is toggled
          mainMenu.closeMobileMain();

          if (mainMenu.searchForm.classList.contains('search-form-open')) {
            mainMenu.searchForm.classList.remove('search-form-open');
            mainMenu.searchBtn.classList.remove('search-btn-active');
          } else {
            mainMenu.searchForm.classList.add('search-form-open');
            mainMenu.searchBtn.classList.add('search-btn-active');
            mainMenu.searchForm.getElementsByClassName('form-search')[0].focus(); // Puts cursor in search form on open
          }
        },

        closeSearch: function closeSearch() {
          mainMenu.searchForm.classList.remove('search-form-open');
          mainMenu.searchBtn.classList.remove('search-btn-active');
        },

        /**
         * Sets the menu height at large screen sizes
         *
         * Menu height is set to the total height of all the non-image items. That way they fill the first column,
         * and image items get their own columns.
         *
         */
        setMenuHeight: function setMenuHeight() {

          // Return if this is a small screen.
          if (this.getBreakpoint() == 'small') {
            return;
          }

          if (this.subMenus.length > 0) {
            var submenusArray = Array.from(this.subMenus);

            submenusArray.forEach(function (submenu) {

              // Gets total height of all non-image items
              var submenuItems = Array.from(submenu.getElementsByClassName('sub-menu-item'));
              var submenuHeight = 0;
              submenuItems.forEach(function (subsub) {
                submenuHeight += subsub.clientHeight;
              });
              var heightArray = [];
              heightArray.push(submenuHeight);

              // Gets height of tallest image item

              var submenuImages = Array.from(submenu.getElementsByClassName('sub-menu-image-item'));
              submenuImages.forEach(function (subImage) {
                heightArray.push(subImage.clientHeight);
              });

              // Sets dropdown height
              submenu.style.height = Math.max.apply(Math, heightArray) + 'px';
            });
          }
        },

        /**
         * Adds listener to mobile toggler
         */
        mobileMainListener: function mobileMainListener() {

          if (this.getBreakpoint() == 'large') {
            return;
          }

          if (this.mobileToggler !== 'undefined') {
            this.mobileToggler.addEventListener('click', this.mobileMainToggle, false);
          }
        },

        /**
         * Toggle function for main menu
         *
         */
        mobileMainToggle: function mobileMainToggle() {

          if (this.getBreakpoint() == 'large') {
            return;
          }

          mainMenu.closeSearch();

          if (mainMenu.headerWrapper == 'undefined') {
            return;
          }

          if (mainMenu.headerWrapper.classList.contains('main-menu-open')) {
            mainMenu.headerWrapper.classList.remove('main-menu-open');
          } else {
            mainMenu.headerWrapper.classList.add('main-menu-open');
          }
        },

        /**
         * Function to close mobile menu
         */
        closeMobileMain: function closeMobileMain() {
          if (mainMenu.headerWrapper.classList.contains('main-menu-open')) {
            mainMenu.headerWrapper.classList.remove('main-menu-open');
          }
        },

        /**
         * Adds listeners to menu items that have submenus
         */
        mobileSubListener: function mobileSubListener() {

          // Return if this is a large screen.
          if (this.getBreakpoint() == 'large') {
            return;
          }

          var menuItems = this.topUl.getElementsByClassName('top-sub-menu-item');

          var _loop = function _loop() {

            var menuItem = menuItems[i];
            menuItems[i].addEventListener('click', function (e) {
              mainMenu.toggleSubmenu(e, menuItem);
            });
          };

          for (var i = 0; i < menuItems.length; i++) {
            _loop();
          }
        },

        /**
         * Function for toggling submenus
         * @param e
         * @param parentItem
         */
        toggleSubmenu: function toggleSubmenu(e, parentItem) {

          var subMenu = parentItem.getElementsByClassName('sub-menu')[0];

          // Return if there's no submenu in this item
          if (typeof subMenu == 'undefined') {
            return;
          }

          // Prevent default link if there's a submenu
          e.preventDefault();

          if (subMenu.classList.contains('sub-menu-active')) {
            parentItem.classList.remove('sub-menu-parent-active');
            subMenu.classList.remove('sub-menu-active');
            subMenu.style.height = '0';
          } else {
            parentItem.classList.add('sub-menu-parent-active');
            subMenu.classList.add('sub-menu-active');
            subMenu.style.height = 'auto';
          }
        },

        /**
         *
         */
        unsetSubheight: function unsetSubheight() {

          if (this.getBreakpoint() == 'large') {
            return;
          }

          var subMenus = this.topUl.getElementsByClassName('sub-menu');
          for (var i = 0; i < subMenus.length; i++) {
            subMenus[i].style.height = '0';
          }
        },

        /**
         * Function to call on window resize load.
         */
        resize: function resize() {
          this.initMenu();
          this.unsetSubheight();
          this.closeSearch();
        },

        /**
         * Initialize menu
         */
        initMenu: function initMenu() {

          this.getBreakpoint();
          this.setMenuHeight();
          this.mobileSubListener();
          this.mobileMainListener();
          this.unsetSubheight();

          // Remove existing sticky bits, if they exist
          if (settings.headerStickyBits) {
            settings.headerStickyBits.cleanup();
          }

          if (settings.productTabsStickyBits) {
            settings.productTabsStickyBits.cleanup();
          }

          // If there's a toolbar, add that to the offset for header and product tabs
          var toolBarOffset = 0,
              headerOffset = 0;

          if (Drupal.toolbar) {
            toolBarOffset = Drupal.toolbar.models.toolbarModel.attributes.offsets.top;
          }

          // Make header sticky if we're at large breakpoint

          // Test for sticky support
          settings.positionStickySupport = function () {
            var el = document.createElement('a'),
                mStyle = el.style;
            mStyle.cssText = "position:sticky;position:-webkit-sticky;position:-ms-sticky;";
            return mStyle.position.indexOf('sticky') !== -1;
          }();

          if (settings.positionStickySupport) {
            if (this.getBreakpoint() == 'large') {
              settings.headerStickyBits = stickybits('.header', { stickyBitStickyOffset: toolBarOffset });
              headerOffset = $(document).find('.header').height();
            }

            // offset product tabs by height of toolbar and header (header only at large screens)
            if ($(context).find('.product-tabs')) {
              var productTabsOffset = headerOffset + toolBarOffset;
              settings.productTabsStickyBits = stickybits('.product-tabs', {
                stickyBitStickyOffset: productTabsOffset
              });
            }
          }

          if (this.searchBtn != null && this.searchForm != null) {
            // Add initial event listener to search open btn
            this.searchBtn.addEventListener('click', this.toggleSearch);
            this.searchCloseBtn.addEventListener('click', this.toggleSearch);
          }
        }
      };

      // Initialize menu on document load
      mainMenu.initMenu();

      /* Resets heights on window resize. */
      var resetMenu = debounce(function () {
        mainMenu.resize();
      }, 20);

      window.addEventListener('resize', function () {
        resetMenu();
      }, false);
    }
  };
})(jQuery, Drupal);
