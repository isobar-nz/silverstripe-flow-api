<div id="$FlowModalID.ATT" class="modal fade grid-field-flow-sync" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <% if $FlowModalTitle %>
                    <h2 class="modal-title">$FlowModalTitle</h2>
                <% end_if %>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <% if $FlowIframe %>
                    <iframe src="$FlowIframe.ATT" width="100%%" height="400px" frameBorder="0"></iframe>
                <% else_if $FlowForm %>
                    $FlowForm
                <% end_if %>
            </div>
        </div>
    </div>
</div>
