
window.jQuery.entwine('ss', ($) => {

  // CMS admin extensions
  $('button[name=action_doFlowSync]').entwine({
    onmatch() {
      this._super();
    },
  });
});
