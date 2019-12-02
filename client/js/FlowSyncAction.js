
window.jQuery.entwine('ss', ($) => {

  // CMS admin extensions
  $('input[data-hides]').entwine({
    onmatch() {
      this._super();
      console.log('hello');
    },
    onunmatch() {
      this._super();
    },
    onchange() {
      this._super();
      console.log('hello');
    },
  });

});
