<div class="member-type-wrapper mb-5" id="dailyMemberSelectionWrapper">
    <p class="fs-4 mb-0">Select Member Type</p>
    <div class="alert alert-warning" role="alert">
        Please select the correct daily membership option depending on the number of players 
    </div>

    <div class="card">
        <div class="card-body">

            <div class="member_daily_booking_fee_form">
                <input class="form-check-input" type="radio" name="daily_member_type" id="daily_member_type_member" value="member_daily_booking_fee" data-type-price="0" data-pax="1"> 
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="daily_member_type" id="daily_member_type_individual" value="Individual" data-type-price="500" data-pax="1" checked> 
                <label class="form-check-label" for="daily_member_type_individual"><span class="fw-bold">Individual</span> (1 player) - 500THB</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="daily_member_type" id="daily_member_type_couple" value="Couple" data-type-price="800"  data-pax="2">
                <label class="form-check-label" for="daily_member_type_couple"><span class="fw-bold">Couple</span> (2 players) - 800THB</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="daily_member_type" id="daily_member_type_family" value="Family" data-type-price="950"  data-pax="2">
                <label class="form-check-label" for="daily_member_type_family"><span class="fw-bold">Family</span> (More than 2 players) - 950THB</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="daily_member_type" id="daily_member_type_junior" value="Junior" data-type-price="350"  data-pax="1">
                <label class="form-check-label" for="daily_member_type_junior"><span class="fw-bold">Junior</span> (Under 18 years old) - 350THB</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="daily_member_type" id="daily_member_type_1adult1child" value="1 adult 1 child" data-type-price="650"  data-pax="2">
                <label class="form-check-label" for="daily_member_type_1adult1child"><span class="fw-bold">1 adult 1 child</span> - 650THB</label>
            </div>
            <?php /* if( $memberType == 'admin'): ?>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="daily_member_type" id="daily_member_type_academy" value="Academy" data-type-price="0" disabled>
                <label class="form-check-label" for="daily_member_type_academy">Academy</label>
            </div>
            <?php endif; */ ?>

        </div>
    </div>
</div>