// Opens and closes search in header

ready(function () {

  function openSearch() {
    searchForm.classList.add('search-form-open');
    searchBtn.classList.add('search-btn-active');
    searchBtn.addEventListener('click', closeSearch);
    searchCloseBtn.addEventListener('click', closeSearch);
    searchBtn.removeEventListener('click', openSearch);
    searchForm.getElementsByClassName('form-search')[0].focus(); // Puts cursor in search form on open
  }

  function closeSearch() {
    searchForm.classList.remove('search-form-open');
    searchBtn.classList.remove('search-btn-active');
    searchBtn.addEventListener('click', openSearch);
    searchBtn.removeEventListener('click', closeSearch);
  }

  var searchBtn = document.getElementById('js-search-btn');
  var searchForm = document.getElementById('js-page-search');
  var searchCloseBtn = document.getElementById('js-search-close-btn');

  if (searchBtn != null && searchForm != null) {

    // Add initial event listener to search open btn
    searchBtn.addEventListener('click', openSearch);
  }
});
