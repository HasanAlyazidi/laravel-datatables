/*!
 * hasanalyazidi/laravel-datatables — client bootstrap.
 *
 * Initialises <table class="datatable"> (rows already in the HTML) and
 * <table class="datatable-server"> (driven by the data-datatable-config
 * attribute that <x-datatable> / @datatable emit), and wires the export
 * buttons. Works with DataTables 1.10.8+ and 2.x, in plain ES5.
 *
 * Optional per-app settings, set BEFORE this script loads:
 *
 *   window.laravelDataTables = {
 *     defaults: { ... any DataTables options ... },
 *     allLabel: 'All',      // label for the -1 ("everything") page size
 *     clientSide: false     // keep your own .datatable initialiser
 *   };
 */
(function (window, $) {
  'use strict';

  if (!$ || !$.fn || !$.fn.dataTable) {
    if (window.console && window.console.error) {
      window.console.error(
        'laravel-datatables: jQuery DataTables is not loaded. Load DataTables (1.10.8+ or 2.x) '
        + 'and its Bootstrap integration before this script — or use the @dataTablesScripts '
        + 'directive, which emits them for you.'
      );
    }

    return;
  }

  var settings = window.laravelDataTables || {};
  var allLabel = settings.allLabel || 'All';

  var base = $.extend({}, {
    stateSave: false,
    pageLength: 25,
    paging: true,
    searching: true,
    order: [[0, 'asc']],
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, allLabel]]
  }, settings.defaults || {});

  function pick(value, fallback) {
    return value === undefined || value === null ? fallback : value;
  }

  // DataTables also accepts a flat lengthMenu ([10, 25, 50]); normalise to
  // the two-array form so the "All" trim below always has labels to edit.
  function twoFormLengthMenu(menu) {
    if (menu.length === 2 && $.isArray(menu[0])) {
      return [menu[0].slice(), menu[1].slice()];
    }

    var values = menu.slice();
    var labels = [];

    for (var i = 0; i < values.length; i++) {
      labels.push(values[i] === -1 ? allLabel : values[i]);
    }

    return [values, labels];
  }

  // Column::index() columns: the row number, following page and offset.
  function indexRenderer(data, type, row, meta) {
    return meta.row + meta.settings._iDisplayStart + 1;
  }

  $(function () {
    // Client-side tables: all rows are already in the HTML. Skipped when the
    // app keeps its own initialiser (clientSide: false), and any table that
    // is already a DataTable is left alone — so adding this script to a page
    // with a legacy initialiser can never double-initialise.
    if (settings.clientSide !== false) {
      $('table.datatable').each(function () {
        if ($.fn.dataTable.isDataTable(this)) {
          return;
        }

        $(this).DataTable($.extend({}, base, {
          columnDefs: [{ targets: 'no-sort', orderable: false }]
        }));
      });
    }

    // Server-side tables. errMode 'none' stops DataTables popping up a
    // browser alert on its internal errors; those are handled by error.dt
    // below, and transport failures by the ajax error callback.
    $.fn.dataTable.ext.errMode = 'none';

    $('table.datatable-server').each(function () {
      if ($.fn.dataTable.isDataTable(this) || !this.getAttribute('data-datatable-config')) {
        return;
      }

      var el = this;
      var config = JSON.parse(el.getAttribute('data-datatable-config'));
      el.removeAttribute('data-datatable-config');

      $(el).data('dtExporters', config.exporters || []);

      var $containers = $(config.filters || '.datatable-filters');
      var reloadedKey = 'dt-reloaded:' + el.id;

      var lengthMenu = twoFormLengthMenu(pick(config.lengthMenu, base.lengthMenu));

      if (!config.allowAll) {
        // Drop the "All" (-1) choice. The server caps it anyway, so leaving
        // it in the menu would promise something it will not deliver.
        var sizes = [];
        var labels = [];

        for (var i = 0; i < lengthMenu[0].length; i++) {
          if (lengthMenu[0][i] !== -1) {
            sizes.push(lengthMenu[0][i]);
            labels.push(lengthMenu[1][i]);
          }
        }

        lengthMenu = [sizes, labels];
      }

      function filterValues() {
        var values = {};

        $containers.find(':input[name]').serializeArray().forEach(function (field) {
          if (field.value === '') {
            return;
          }

          if (field.name.slice(-2) === '[]') {
            var name = field.name.slice(0, -2);
            (values[name] = values[name] || []).push(field.value);

            return;
          }

          values[field.name] = field.value;
        });

        return values;
      }

      var columns = config.columns || [];

      for (var c = 0; c < columns.length; c++) {
        if (columns[c].index) {
          columns[c].data = null;
          columns[c].defaultContent = '';
          columns[c].render = indexRenderer;
        }
      }

      // An expired session answers a draw with the login page (200 + JSON
      // parse failure) or a 401/419: reload once so the middleware shows
      // the login screen. The flag — cleared on the next good draw —
      // stops looping when the endpoint is simply broken. Lives in
      // ajax.error because that callback REPLACES DataTables' own error
      // handling; error.dt only sees internal errors.
      //
      // When storage is blocked entirely, the flag cannot persist — then
      // NEVER reload (a loop would be worse) and just log.
      function reloadOnceOr(logArgs) {
        var alreadyReloaded = '1';

        try {
          alreadyReloaded = window.sessionStorage.getItem(reloadedKey);

          if (!alreadyReloaded) {
            window.sessionStorage.setItem(reloadedKey, '1');
          }
        } catch (err) {
          alreadyReloaded = '1';
        }

        if (!alreadyReloaded) {
          window.location.reload();

          return;
        }

        if (window.console && window.console.error) {
          window.console.error.apply(window.console, logArgs);
        }
      }

      var options = $.extend({}, base, {
        lengthMenu: lengthMenu,
        processing: true,
        serverSide: true,
        ajax: {
          url: config.ajax,
          data: function (d) { d.filters = filterValues(); },
          error: function (xhr, textStatus) {
            reloadOnceOr(['DataTable ajax error:', xhr.status, textStatus]);
          }
        },
        columns: columns
      });

      // Per-table overrides from the component/directive; null means "no
      // override" and keeps the shared default.
      var overridable = ['responsive', 'stateSave', 'pageLength', 'paging', 'searching', 'order'];

      for (var o = 0; o < overridable.length; o++) {
        var key = overridable[o];
        var value = pick(config[key], base[key]);

        if (value !== undefined) {
          options[key] = value;
        }
      }

      var table = $(el).DataTable(options);

      // DataTables' internal errors (bad column data and the like) get the
      // same reload-once treatment — a stale cached page after a deploy
      // fixes itself the same way an expired session does.
      $(el).on('error.dt', function (e, dtSettings, techNote, message) {
        reloadOnceOr(['DataTable error:', message]);
      });

      $(el).on('draw.dt', function () {
        try {
          window.sessionStorage.removeItem(reloadedKey);
        } catch (err) {
          // Storage blocked; nothing to clear.
        }
      });

      $containers.on('change', ':input[name]', function () {
        table.ajax.reload();
      });
    });

    // Export buttons: download whatever the target table is showing, with
    // its filters, search and sorting. Bound once on the document rather
    // than per table, because a button may sit anywhere on the page.
    $(document).on('click', '[data-datatable-export]', function (e) {
      e.preventDefault();

      var target = $(this).data('datatable-target');
      var $table = target ? $(target) : $('table.datatable-server').first();

      if (!$table.length || !$.fn.dataTable.isDataTable($table)) {
        if (window.console && window.console.error) {
          window.console.error('No datatable found for export button');
        }

        return;
      }

      var table = $table.DataTable();
      var params = $.extend({}, table.ajax.params(), { export: $(this).data('datatable-export') });
      var url = table.ajax.url();

      window.location = url + (url.indexOf('?') === -1 ? '?' : '&') + $.param(params);
    });

    // A hand-written button can name a format its table does not actually
    // offer, so hide those — the theme's own dropdown is already built from
    // the right list. Then hide any wrapper left with nothing visible in it.
    $('[data-datatable-export]').each(function () {
      var target = $(this).data('datatable-target');
      var $table = target ? $(target) : $('table.datatable-server').first();
      var allowed = $table.data('dtExporters') || [];

      if (allowed.indexOf(String($(this).data('datatable-export'))) === -1) {
        $(this).hide();
      }
    });

    $('[data-datatable-export-group]').each(function () {
      var anyVisible = $(this).find('[data-datatable-export]').filter(function () {
        return $(this).css('display') !== 'none';
      }).length > 0;

      if (!anyVisible) {
        $(this).hide();
      }
    });
  });
})(window, window.jQuery);
