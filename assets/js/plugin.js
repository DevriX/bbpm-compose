jQuery(document).ready(function($){
  if ( "object" !== typeof BBPMC.users ) {
    BBPMC.users = [];
  }

  var toggleButton = function() {
    var enable
      , cr
      , f = $('#bbpm-compose');
    if ( !$.trim($('textarea[name="bbpmc_text"]',f).val()) ) {
      enable = false;
    } else if ( !$.trim($('#rc_criteria',f).val()) ) {
      enable = false
    } else {
      cr = $('#rc_criteria',f).val();
      if ( "search" === cr ) {
        enable = BBPMC.users && BBPMC.users.length;
      } else if ( "role" === cr ) {
        enable = BBPMC.selected_role && BBPMC.selected_role.length
      } else if ( "all" === cr ) {
        enable = true;
      }
    } 
        console.log('enable', enable, 'cr', cr);
    if ( enable ) {
      return $('input[type="submit"]',f).last().removeAttr('disabled');
    } else {
      return $('input[type="submit"]',f).last().attr('disabled', 'disabled');
    }
  }

  BBPMC.toggleButton = toggleButton;

  $('#rc_criteria').trigger('change');
  $('#rc_criteria').change(function(e){
    e.preventDefault();
    var v = $(this).val()
      , s = $('#cr-search')
      , r = $('#cr-roles')
      , a = $('#cr-all');
    if ( 'role' == v ) {
      BBPMC.criteria = 'role';
      s.fadeOut('fast');
      a.fadeOut('fast');
      r.fadeIn('fast', function(){
        $('input', $(this)).first().focus();
      });
    } else if ( 'all' == v ) {
      BBPMC.criteria = 'all';
      s.fadeOut('fast');
      r.fadeOut('fast');
      a.fadeIn('fast');
    } else if ( 'search' == v ) {
      BBPMC.criteria = 'search';
      s.fadeIn('fast', function(){
        $('input', $(this)).first().focus();
      });
      a.fadeOut('fast');
      r.fadeOut('fast');
    } else {
      s.fadeOut('fast');
      a.fadeOut('fast');
      r.fadeOut('fast');
    }
    BBPMC.toggleButton();
  });

  var onBtnClk = function(element) {
    var b = $(element)
      , l = b.closest('li')
      , i = parseInt(l.attr('data-id'))
      , u = BBPMC.users||[]
      , p = $('#cr-search .cr-picked')
      , r = $('#cr-search .cr-results')
    BBPMC.toggleButton();
    if ( b.hasClass('add') ) {
      if ( BBPMC.users.indexOf(i) > -1 ) return;
      BBPMC.users.push(i);
      BBPMC.toggleButton();
      if ( BBPMC.users && BBPMC.users.length ) {
        p.append(l.prop('outerHTML'));
        $('#bbpm-compose li .button').click(function(e){
          return onBtnClk(this)
        });
      } else {
        $('li',p).remove();
      }
      l.fadeOut('fast',function(){
        return $(this).remove();
      })
    } else if (BBPMC.users.length) {
      for ( n in BBPMC.users ) {
        if ( i === parseInt(BBPMC.users[n]) ) {
          BBPMC.users.splice(n,1);
          l.fadeOut('fast',function(){
            $(this).remove();
            return BBPMC.toggleButton();
          });
          return;
        }
      }
    }
  }

  $('#bbpm-compose li .button').click(function(e){
    return onBtnClk(this)
  });

  $('#cr-search input[type="text"]').keypress(function(e){
    if ( 13 === (e.keyCode||e.which) ) {
      var i = $(this)
        , v = $.trim(i.val())
        , x = BBPMC.users||[]
        , data = {action: 'bbpm-compose', 'search_query': v, 'exclude': x, 'nonce': BBPMC.nonce}
      
      e.preventDefault();

      if ( i.attr('disabled') ) return;
      i.attr('disabled', 'disabled');
      jQuery.ajax({ url: BBPMC.ajaxurl, type: 'post', 'data': data, success: function(data){
        i.removeAttr('disabled');
        var res = $('.cr-results');
        res.html('');
        if ( data.success ) {
          if ( data.users && data.users.length ) {
            $.each(data.users,function(i,usr){
              res.append( '<li data-id="'+usr.ID+'"><img src="'+usr.avatar+'" height="15" width="15" />&nbsp;'+usr.display_name+'&nbsp;<span class="button add" title="Add">&plus;</span><span class="button rem" title="Remove">&times;</span></li>' )
              $('#bbpm-compose li .button').click(function(e){ return onBtnClk(this) });
            });
          } else {
            console.log('focus');
            i.select().focus();
          }
        } else {
          alert(data.message||'Error occured')
        }
      }, error: function() {
        i.removeAttr('disabled');
        alert('Error occured while searching.');
      }});
      return false;
    }
  });

  $('#cr-roles input[name="role"]').change(function(e){
    BBPMC.selected_role = $.trim($(this).val());
    e.preventDefault();
    BBPMC.toggleButton();
  });

  $('textarea[name="bbpmc_text"]').change(function(e){
    e.preventDefault();
    // message will be cleared as soon as sent. To get it back, run BBPMC.autosave on your browser console // (Ctrl|Cmd)+Shift+J
    BBPMC.autosave = $(this).val();
    return BBPMC.toggleButton();
  });

  $('#bbpm-compose').submit(function(evt){  
    evt.preventDefault();

    var f = $(this)
      , tx = $('textarea[name="bbpmc_text"]',f)
      , cr = BBPMC.criteria
      , sel = $('#rc_criteria',f)
      , users
      , reqData = {action: 'bbpm-compose-send', 'nonce': BBPMC.nonce}

    BBPMC.toggleButton();

    if ( !$.trim(tx.val()) ) {
      alert('Please type a message!');
      return tx.focus();
    } else {
      reqData.message = $.trim(tx.val());
    }

    if ( !cr ) {
      alert('Please select a criteria!');
      return sel.focus();
    }

    switch ( cr.toLowerCase() ) {
      case 'search':
        if ( BBPMC.users && BBPMC.users.length ) {
          users = BBPMC.users;
        }
        break;
      case 'role':
        if ( BBPMC.selected_role && BBPMC.selected_role.length ) {
          users = BBPMC.selected_role
        }
        break;
      case 'all':
        users = '_all';
        break;
    }

    if ( users && users.length ) {
      if ( "string" == typeof users ) {
        if ( '_all' === users ) {
          reqData.all = true;
        } else {
          reqData.role = users;
        }
      } else if ( "object" == typeof users ) {
        reqData.users = BBPMC.users;
      }
    }

    if ( reqData.all || reqData.role || reqData.users ) {

      var btn = $('input[type="submit"]',f).last();
      if ( btn.attr('disabled') ) return;
      btn.attr('disabled','disabled').attr('data-val',function(){
        return $(this).val();
      }).val('Sending ..');

      $.ajax({ url: BBPMC.ajaxurl, type: 'POST', 'data': reqData, success: function(response){
        if ( response.success && response.message ) {
          alert(response.message);

          $('textarea[name="bbpmc_text"]',f).val('');
          $('#rc_criteria',f).val('0');
          $('#cr-search li').remove();
          $('#cr-search input[type="text"]').val('');          
          $('#cr-roles input[type="radio"]').prop('checked',false);

          BBPMC.selected_role = null;
          BBPMC.users = [];
          BBPMC.last_sent = reqData.message;
          BBPMC.toggleButton();

        } else {
          alert(response.message||'Error occured while sending');
        }
        return btn.removeAttr('disabled').val(function(){
          return $(this).attr('data-val');
        });
      }, error: function(){
        alert('Error occured while sending this message. Please try again.');
        return btn.removeAttr('disabled').val(function(){
          return $(this).attr('data-val');
        });
      }});
    } else { return alert('Please select at least one recipient for this message.'); }
  });
});