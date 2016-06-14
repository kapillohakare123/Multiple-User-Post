var $ = jQuery.noConflict();

$(function() {
   
    function log( message ) {
      $( "<div>" ).html( message ).prependTo( "#log" );
      $( "#log" ).scrollTop( 0 );
    }
    $( "#birds" ).autocomplete({
      source: script_object.ajaxurl+ "?action=multiple_user_post_getpage",
      select: function( event, ui ) {
        log( ui.item ?
           ''+ ui.item.value +'<input  value="'+ ui.item.id +'" name="meta-box-user-store[]" type="hidden" >'
        :
          "Nothing selected, input was " + this.value );
      }
    });
  });