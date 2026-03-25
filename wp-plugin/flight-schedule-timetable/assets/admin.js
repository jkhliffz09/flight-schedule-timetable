(function () {
  var tabs = document.querySelectorAll('.fst-tab');
  var panels = document.querySelectorAll('.fst-panel');

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
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      activate(tab.getAttribute('data-tab'));
    });
  });
})();
