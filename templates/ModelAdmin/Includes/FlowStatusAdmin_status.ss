<div class="cms-content-view">

  <% with $StatusData %>
    <div class="message $Status">{$Message}</div>

    <pre style="max-height: 500px; overflow-y: scroll;">
        {$Data}
    </pre>
  <% end_with %>

</div>
