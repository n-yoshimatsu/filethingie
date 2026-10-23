/* File Thingie UI. Original: Andreas Haugstrup Pedersen. MIT (see LICENSE). */
(function ($) {
  'use strict';
  $.fn.ft_upload = function (options) {
    return this.each(function () {
      const box = $(this);
      let sequence = 0;
      function addFile() {
        const input = this;
        if (!input.files.length) return;
        if (!box.find('#files_list').length) box.find('#uploadsection').after($('<h3>').text(options.header), $('<ul>', {id: 'files_list'}));
        $(input).addClass('safarihide');
        const originalName = $('<input>', {type: 'hidden', name: 'upload_names[' + input.name + ']', value: input.files[0].name}).insertBefore(input);
        const next = $('<input>', {type: 'file', size: 12, class: 'upload', name: 'localfile-' + (++sequence)}).on('change', addFile);
        $(input).before(next);
        const item = $('<li>').text(input.files[0].name + ' ');
        $('<button>', {type: 'button', title: options.cancel}).text('[x]').on('click', function () { item.remove(); $(input).remove(); originalName.remove(); }).appendTo(item);
        box.find('#files_list').append(item);
      }
      box.find('input[type=file]').on('change', addFile);
      box.on('submit', function () { box.find('#uploadbutton').hide().after($('<p>', {class: 'info'}).text(options.upload)); });
    });
  };
  $.fn.ft_filelist = function (options) {
    return this.each(function () {
      const table = $(this);
      table.find('td.details span.hide').on('click', function () {
        $(this).closest('tr').next('.filedetails').remove(); $(this).hide().prev().show();
      });
      table.find('td.details span.show').on('click', function () {
        const toggle = $(this), row = toggle.closest('tr');
        if (!toggle.hasClass('writeable')) return;
        const file = row.attr('data-filename');
        const actions = {rename: options.rename_link, move: options.move_link, del: options.del_link};
        if (row.hasClass('file')) actions.duplicate = options.duplicate_link;
        if (options.canunzip && file.toLowerCase().endsWith('.zip')) actions.unzip = options.unzip_link;
        if (options.advancedactions === 'true') { actions.chmod = options.chmod_link; actions.symlink = options.symlink_link; }
        Object.keys(options.fileactions).forEach(function (key) { if (toggle.hasClass(key)) actions[key] = options.fileactions[key].link; });
        const details = $('<tr>', {class: 'filedetails'}), cell = $('<td>', {colspan: 3}).appendTo(details);
        const menu = $('<ul>', {class: 'navigation'}).appendTo(cell);
        const form = $('<form>', {method: 'post', action: options.formpost}).appendTo(cell), controls = $('<div>').appendTo(form);
        const label = $('<label>').appendTo(controls), value = $('<input>', {type: 'text', name: 'newvalue', class: 'newvalue', size: 18}).appendTo(controls);
        const submit = $('<input>', {type: 'submit', class: 'submit'}).appendTo(controls);
        const act = $('<input>', {type: 'hidden', name: 'act', class: 'act'}).appendTo(controls);
        [['file', file], ['dir', options.directory], ['csrf_token', $('meta[name="csrf-token"]').attr('content')]].forEach(function (entry) {
          $('<input>', {type: 'hidden', name: entry[0], value: entry[1]}).appendTo(controls);
        });
        function select(key) {
          menu.find('li').removeClass('selected'); menu.find('li.' + key).addClass('selected');
          act.val(key === 'del' ? 'delete' : key);
          const editable = ['rename', 'move', 'duplicate', 'chmod', 'symlink'].includes(key);
          value.toggle(editable); submit.show().val(options.ok);
          label.text(options[key] || '');
          if (key === 'rename') value.val(file);
          if (key === 'move') value.val('');
          if (key === 'duplicate' || key === 'symlink') {
            const dot = file.lastIndexOf('.'), suffix = key === 'duplicate' ? '(copy)' : '(link)';
            value.val(dot > 0 ? file.slice(0, dot) + suffix + file.slice(dot) : file + suffix);
          }
          if (key === 'chmod') value.val((toggle.attr('class').match(/perm-([0-7]{3,4})/) || ['', '644'])[1]);
          if (key === 'del') {
            submit.val(options.del_button);
            if (row.hasClass('dir') && !toggle.hasClass('empty')) { label.text(options.del_warning); submit.hide(); }
          }
          if (key === 'unzip') submit.val(options.unzip_button);
          if (options.fileactions[key]) { label.text(options.fileactions[key].text); submit.val(options.fileactions[key].button); }
          if (editable) value.trigger('focus').trigger('select');
        }
        Object.keys(actions).forEach(function (key) {
          $('<li>', {class: key}).text(actions[key]).on('click', function () { select(key); }).appendTo(menu);
        });
        row.after(details); toggle.hide().next().show(); select('rename');
      });
    });
  };
  $.fn.ft_search = function (options) {
    return this.each(function () {
      $('#searchform').on('submit', function (event) { event.preventDefault(); $('#dosearch').trigger('click'); });
      $('#dosearch').on('click', function () {
        const list = $('<dl>', {id: 'searchlist'}).append($('<dt>').text(options.loading));
        $('#searchresults').empty().append($('<h3>').text(options.header), list);
        $.post(options.formpost, {method: 'ajax', act: 'search', q: $('#q').val(), type: $('#type').is(':checked'), dir: options.directory})
          .done(function (data) { list.html(data); }).fail(function () { list.text('Search failed. Reload and try again.'); });
        return false;
      });
    });
  };
})(jQuery);
