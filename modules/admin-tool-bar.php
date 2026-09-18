<div class="mb-3">
    <div class="alert alert-light search-member-panel display" id="searchMemberPanel">
        <label for="member_detail">Search member</label>
        <div class="input-group mb-3">
            <input type="text" class="form-control" name="member_detail" id="member_detail" placeholder="Enter keyword">
            <select class="form-select" name="search_type" id="search_type">
                <option value="member_name" selected>Search by name</option>
                <option value="member_number">Search by member number</option>
            </select>
            <input type="hidden" id="search_member_params" name="search_member_params" value="<?= ($param_booking_type)? $param_booking_type : 'member' ?>">
            <button class="btn btn-outline-secondary" type="button" id="searchMember" data-bs-toggle="modal" data-bs-target="#memberSearchResults">
                Search member</button>
        </div>
    </div>

    <div class="search-member-result hide-panel bg-success-subtle p-2 rounded" id="memberSearchDetail">
        <h4>Selected member</h4>
        <small>Member name</small><br>
        <p id="memberName"></p>

        <small>Member number</small><br>
        <p id="memberNumber"></p>
    </div>
</div>


<!-- Member modal -->
<div class="modal fade" id="memberSearchResults" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="memberSearchResultsLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="memberSearchResultsLabel">Member search results</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="member-result-list" id="memberResultList">
            <div class="loader-member-list text-center">
                <img src="images/ball_loading.gif" alt="" width="100">
                <p>Searching...</p>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>