(function () {
  var tabs = document.querySelectorAll('.fst-tab');
  var panels = document.querySelectorAll('.fst-panel');
  var paginationButtons = document.querySelectorAll('[data-fst-page-target]');

  if (!tabs.length) {
    return;
  }

  function activate(tabName) {
    tabs.forEach(function (tab) {
      tab.classList.toggle('is-active', tab.getAttribute('data-tab') === tabName);
    });

    panels.forEach(function (panel) {
      panel.classList.toggle('is-active', panel.getAttribute('data-panel') === tabName);
    });

    if (window.location.hash !== '#' + tabName) {
      history.replaceState(null, '', '#' + tabName);
    }
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      activate(tab.getAttribute('data-tab'));
    });
  });

  function setPage(targetId, page) {
    var rows = document.querySelectorAll('[data-fst-page-row="' + targetId + '"]');
    var buttons = document.querySelectorAll('[data-fst-page-target="' + targetId + '"]');
    var currentPage = String(page);

    rows.forEach(function (row) {
      row.hidden = row.getAttribute('data-fst-page') !== currentPage;
    });

    buttons.forEach(function (button) {
      button.classList.toggle('is-active', button.getAttribute('data-fst-page') === currentPage);
    });
  }

  paginationButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      setPage(button.getAttribute('data-fst-page-target'), button.getAttribute('data-fst-page'));
      activate('analytics');
    });
  });

  var initialTab = window.location.hash ? window.location.hash.replace('#', '') : 'kpi';
  activate(initialTab);
})();
