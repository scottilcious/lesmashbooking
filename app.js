//const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
//const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));

function formatNumber(number){
    number = number.toFixed(2) + '';
    x = number.split('.');
    x1 = x[0];
    x2 = x.length > 1 ? '.' + x[1] : '';
    var rgx = /(\d+)(\d{3})/;
    while (rgx.test(x1)) {
        x1 = x1.replace(rgx, '$1' + ',' + '$2');
    }
    return x1 + x2;
    
}

function updateMemberCreditBookingState(totalFee){
    const adminViewInput = document.getElementById("admin_view");
    const bookingTypeInput = document.getElementById("booking_type");
    const creditInput = document.getElementById("member_current_credit");
    const creditOption = document.getElementById("payment_option_credit");
    const warning = document.getElementById("creditWarning");
    const bookButton = document.getElementById("bookButton");

    if (!adminViewInput || !bookingTypeInput || !creditInput || !creditOption || !warning || !bookButton) {
        return;
    }

    const adminView = adminViewInput.value;
    const bookingType = bookingTypeInput.value;

    if (adminView !== 'false' || bookingType !== 'member booking') {
        return;
    }

    const currentCredit = parseInt(creditInput.value || "0", 10);
    const requiredFee = parseInt(totalFee || "0", 10);
    const selectedSlots = document.querySelectorAll('input[name="court_timeslot[]"]:checked').length;
    const insufficientCredit = selectedSlots > 0 && requiredFee > 0 && currentCredit < requiredFee;

    creditOption.disabled = currentCredit <= 0 || insufficientCredit;
    warning.classList.toggle("d-none", !insufficientCredit);
    bookButton.disabled = insufficientCredit;

    if (insufficientCredit && creditOption.checked) {
        creditOption.checked = false;
    } else if (!creditOption.disabled && selectedSlots > 0) {
        creditOption.checked = true;
    }
}



/*
function checkboxlimit(checkgroup, limit, member_type){
    var member_type = member_type;
    var showalert = false;

    for (var i=0; i<checkgroup.length; i++){
        checkgroup[i].onclick=function(){
        var checkedcount=0;
        for (var i=0; i<checkgroup.length; i++)
            checkedcount+=(checkgroup[i].checked)? 1 : 0
        if (checkedcount>limit){
            showalert = true;
            this.checked=false;
            alert("You can only book a maximum of "+limit+" hours per day.");
            }
        }
    }
}
*/

//BOOKING TIME RULES
function setupBookingRules(groupSelector, member_type) {
  // derive limits from member type
  const rules = (() => {
    const t = (member_type || '').toLowerCase();
    if (t === 'individual' || t === 'junior') {
      return { totalLimit: 2, maxPerTimeslot: 1 };
    }
    if (t === 'couple' || t === '1 adult 1 child') {
      return { totalLimit: 3, maxPerTimeslot: 2 };
    }
    if (t === 'family') {
      return { totalLimit: 4, maxPerTimeslot: 2 };
    }
    if (t === 'admin') {
      return { totalLimit: 240, maxPerTimeslot: 12 };
    }
    // default safest rule if unknown
    return { totalLimit: 2, maxPerTimeslot: 1 };
  })();

  const checkboxes = Array.from(document.querySelectorAll(groupSelector));

  // Guard: if nothing found, do nothing
  if (!checkboxes.length) return;

  function parseValue(val) {
    // Expect "court/timeslot"
    const [court, timeslot] = String(val).split('/');
    return { court: court?.trim(), timeslot: timeslot?.trim() };
  }

  function getWaitlistForTimeslot(timeslot) {
    return checkboxes.find(cb => {
      const parsed = parseValue(cb.value);
      return parsed.court === 'waitlist' && parsed.timeslot === timeslot;
    });
  }

  function getExistingBookingsForTimeslot(timeslot) {
    const waitlist = getWaitlistForTimeslot(timeslot);
    return waitlist ? parseInt(waitlist.dataset.existingMemberBookings || '0', 10) : 0;
  }

  function updateWaitlistQuotaState() {
    const selectedCourtsByTimeslot = new Map();

    for (const cb of checkboxes) {
      const { court, timeslot } = parseValue(cb.value);
      if (!timeslot || court === 'waitlist' || !cb.checked) continue;
      selectedCourtsByTimeslot.set(timeslot, (selectedCourtsByTimeslot.get(timeslot) || 0) + 1);
    }

    for (const cb of checkboxes) {
      const { court, timeslot } = parseValue(cb.value);
      if (court !== 'waitlist' || !timeslot) continue;

      const maxPerTimeslot = parseInt(cb.dataset.maxPerTimeslot || rules.maxPerTimeslot, 10);
      const existingCount = parseInt(cb.dataset.existingMemberBookings || '0', 10);
      const selectedCourtCount = selectedCourtsByTimeslot.get(timeslot) || 0;
      const quotaReached = (existingCount + selectedCourtCount) >= maxPerTimeslot;
      const labelText = cb.closest('label')?.querySelector('.waitlist-label-text');

      if (!cb.dataset.defaultLabel && labelText) {
        cb.dataset.defaultLabel = labelText.textContent;
      }

      if (quotaReached) {
        cb.checked = false;
        cb.disabled = true;
        if (labelText) {
          labelText.textContent = 'Waitlist Unavailable - quota reached';
        }
      } else if (cb.dataset.quotaDisabled !== 'true') {
        cb.disabled = false;
        if (labelText && cb.dataset.defaultLabel) {
          labelText.textContent = cb.dataset.defaultLabel;
        }
      }
    }
  }

  function validate(currentChanged) {
    const checked = checkboxes.filter(cb => cb.checked);

    // 1) Total limit
    if (checked.length > rules.totalLimit) {
      alert(`You can only book a maximum of ${rules.totalLimit} hour(s) per day for your membership.`);
      return false;
    }

    // 2) Per-timeslot (parallel) limit
    const perTimeslot = new Map(); // timeslot -> count
    for (const cb of checked) {
      const { court, timeslot } = parseValue(cb.value);
      if (!timeslot) continue; // skip malformed
      if (court === 'waitlist') continue;
      perTimeslot.set(timeslot, (perTimeslot.get(timeslot) || 0) + 1);
      if ((getExistingBookingsForTimeslot(timeslot) + perTimeslot.get(timeslot)) > rules.maxPerTimeslot) {
        const msg =
          rules.maxPerTimeslot === 1
            ? `You cannot book more than one court at the same time (${timeslot}).`
            : `You can book at most ${rules.maxPerTimeslot} court(s) at the same time (${timeslot}).`;
        alert(msg);
        return false;
      }
    }

    return true;
  }

  // Change handler (uses event delegation per checkbox)
  function onChange(e) {
    const cb = e.target;
    if (cb.type !== 'checkbox') return;

    // Only validate on checking (uncheck is always allowed)
    if (cb.checked) {
      const ok = validate(cb);
      if (!ok) {
        cb.checked = false; // revert the change
      }
      updateWaitlistQuotaState();
    } else {
      updateWaitlistQuotaState();
    }
  }

  updateWaitlistQuotaState();

  // Bind once per checkbox (or you can delegate from a container)
  checkboxes.forEach(cb => {
    cb.removeEventListener('change', onChange); // avoid duplicates if re-initialized
    cb.addEventListener('change', onChange);
  });
}
//BOOKING TIME RULES

function getEffectiveBookingRulesMemberType() {
    const memberTypeInput = document.getElementById("member_type");
    const adminViewInput = document.getElementById("admin_view");
    const adminMemberNumberInput = document.getElementById("current_admin_member_number");

    const memberType = memberTypeInput ? memberTypeInput.value : '';
    const adminView = adminViewInput ? adminViewInput.value : 'false';
    const adminMemberNumber = adminMemberNumberInput ? String(adminMemberNumberInput.value) : '';

    if (adminView === 'true') {
        return adminMemberNumber === '1' ? 'admin' : memberType;
    }

    return memberType;
}

function refreshBookingRules() {
    setupBookingRules('input[name="court_timeslot[]"]', getEffectiveBookingRulesMemberType());
}

function getAvailabilityRequestPayload(date) {
    const adminView = $("#admin_view").val();
    const displayedMemberType = adminView === 'true' ? 'admin' : $("#member_type").val();

    return {
        date: date,
        member_type: displayedMemberType,
        admin_view: adminView,
        member_id: $("#member_id").val(),
        limit_member_type: getEffectiveBookingRulesMemberType()
    };
}

function refreshAvailabilityForSelectedDate() {
    const date = $("#date").val();
    if (!date) return;

    $(".loader-overlay").addClass("display");
    $.post("check_availability.php", getAvailabilityRequestPayload(date), function (data) {
        $("#largeTimeTable").html(data);
        refreshBookingRules();
        $(".loader-overlay").removeClass("display");
    });
}


if( document.getElementById("bookingForm") ){
    //If admin, allow more than 2 
    let member_type = document.getElementById("member_type").value;
    let admin_view = document.getElementById("admin_view").value;
    let limit_numb = 0;
    if( admin_view == "true"){
        limit_numb = 100;
    }else{
        limit_numb = 2;
    }
    // If admin, allow more than 2 
    //console.log("admin:" + admin_view);
    refreshBookingRules();
    /*
    if( admin_view == "true"){
        checkboxlimit(document.forms.bookingForm['court_timeslot[]'], 240);
    }else{
        checkboxlimit(document.forms.bookingForm['court_timeslot[]'], 2);
    }
    */
    
}   

//PRICES: single source of truth is includes/pricing.php, published by menu.php as window.LSC_PRICING.
function lsc_pricing(){
    return window.LSC_PRICING || { courtDay: 160, courtEvening: 280, guestFee: 200, coachFee: 750,
        eveningSlots: ['6-7pm','7-8pm','8-9pm','9-10pm'], dailyFees: {} };
}
function lsc_court_price(timeslot){
    const p = lsc_pricing();
    return p.eveningSlots.indexOf(String(timeslot).trim()) !== -1 ? p.courtEvening : p.courtDay;
}

//CALCULATE COURT BOOKING FEE BASED ON SELECTED TIME
function calc_court_booking_fee(selected_time){
    const booking_type = document.getElementById("booking_type").value;
    if( booking_type == 'member booking' || booking_type == 'non-member'){
        return lsc_court_price(selected_time);
    }
    return 0;
}




//PROCESS AND POPULATE COURT/TIME DATA 
function process_court_time_data(){

    //1. Get all selected court and time checkboxes 
    let all_court_time = document.querySelectorAll('input[name="court_timeslot[]"]:checked');

    //2. Loop through checked checkboxes
    // THEN save value inside all_checked_val 
    // THEN split string with "/" and save [0] in all_court_val and [1] in all_time_val

    let selected_date = document.getElementById('date').value;
    console.log('selected date'+ selected_date);
    
    let all_checked_val = [];
    let all_court_val = [];
    let all_time_val = [];
    let all_waitlist = [];
    let count_checkboxes = 0;
    all_court_time.forEach( checkbox => {
        

        all_checked_val.push(checkbox.value);
        //process value data 
        let splitval = checkbox.value.split("/");
        all_court_val[count_checkboxes] = splitval[0];
        all_time_val[count_checkboxes] = splitval[1];

        //Check if it is court 1/2/3/4 during 21 - 28 dec 2025 then disabled the checkbox
        //if( splitval[0] == 1 && selected_date == '2025-12-28' ){
            //document.querySelectorAll('input[value="'+splitval[0]+'/'+splitval[1]+'"]:disabled');
            //return false;
        //}

        if( splitval[0] == 'waitlist'){
            all_waitlist.push( splitval[0] );
        }

        count_checkboxes++;
        
    });

    //console.log(all_waitlist);

    //3. Populate court and time in DOMs
    let all_selected_courts = document.getElementById("all_selected_courts");
    let all_selected_times = document.getElementById("all_selected_times");

    //Populate courts
    for (let index = 0; index < all_court_val.length; index++) {
        if( index == 0){
            if( document.getElementById("SummaryCourt") ){
                document.getElementById("SummaryCourt").innerHTML = all_court_val[index];
            }

            all_selected_courts.value = all_court_val[index];
            
        }
        if( index > 0){
            if( document.getElementById("SummaryCourt") ){
                document.getElementById("SummaryCourt").innerHTML += ", " + all_court_val[index];
            }

            all_selected_courts.value += ","+all_court_val[index];
        }
    }

    //Popualte times 
    for (let index_t = 0; index_t < all_time_val.length; index_t++) {
        if( index_t == 0){
            if( document.getElementById("SummaryTime") ){
                document.getElementById("SummaryTime").innerHTML = all_time_val[index_t];
            }

            all_selected_times.value = all_time_val[index_t];
        }
        if( index_t > 0 ){
            if( document.getElementById("SummaryTime") ){
                document.getElementById("SummaryTime").innerHTML += ", " + all_time_val[index_t];
            }

            all_selected_times.value += ","+all_time_val[index_t];
        }

    }

    //Compare all checkboxe amount with the legnth of waitlist array. If they are identical, change paymention option to Paylater and disable other paymention option (Credit and Bank Transfer)
    let current_credit = document.getElementById("current_credit").value;
    let admin_view = document.getElementById("admin_view").value;
    //console.log( all_waitlist.length );

    if( ( all_court_time.length > 0 && all_waitlist.length > 0 ) && ( all_waitlist.length ==  all_court_time.length ) &&  admin_view == 'false' ){
        //console.log("all waitlist");
        document.getElementById("paymentLaterOptionWrapper").classList.remove("hide");
        document.getElementById("payment_option_later").checked = true;

        if( document.getElementById("payment_option_qr") != null ){
            document.getElementById("payment_option_qr").checked = false;
        }
        if( document.getElementById("member_current_credit") != null ){
            document.getElementById("member_current_credit").checked = false;
        }

    }else if( ( all_court_time.length > 0 && all_waitlist.length > 0 ) && ( all_waitlist.length ==  all_court_time.length ) &&  admin_view == 'true' ){

        // document.getElementById("payment_option_cash").checked = true;
        document.getElementById("payment_option_credit").checked = true;

    }else if( ( all_court_time.length > 0 || all_waitlist.length > 0 ) &&  admin_view == 'true' ){

        //document.getElementById("payment_option_cash").checked = true;
        document.getElementById("payment_option_credit").checked = true;
        
    }else{
        if( document.getElementById("paymentLaterOptionWrapper") ){
            document.getElementById("paymentLaterOptionWrapper").classList.add("hide");
            document.getElementById("payment_option_later").checked = false;
        }   
        

        if( current_credit > 0){
            document.getElementById("payment_option_credit").checked = true;
        }else{
            document.getElementById("payment_option_qr").checked = true;
        }
        
    }
    
    

}


//CHECK IF ALL IN ARRAY IS WAITLIST
function check_waitlist_array(waitlist_array){
    let result_check_waitlist_array = true;
    for( let waitlist_index = 0; waitlist_index < waitlist_array.length; waitlist_index++ ){
        if( waitlist_array[waitlist_index] != 'waitlist' ){
            result_check_waitlist_array = false;
        }
    }

    return result_check_waitlist_array;
}

//CHECK TIME AND PRICE
function get_price_from_timeslot(court, timeslot){
    return court == 'waitlist' ? 0 : lsc_court_price(timeslot);
}


//CALCULATE AND POPULATE ALL BOOKING FEES
function calculate_all_booking_fees(){
    let booking_type = document.getElementById("booking_type").value;
    let all_checked_court_time = [...document.querySelectorAll('input[name="court_timeslot[]"]:checked')].map(e => e.value);
    let all_checked_court_time_length = all_checked_court_time.length;
    let acccum_daily_member_type_fees = 0;
    let acccum_coach_option_fees = 0;
    let acccum_extra_player_fees = 0;
    let accum_court_booking_fees = 0;
    let selected_extra_player = document.querySelector('#extra_player:checked');
    let extra_player_amount = document.getElementById("extra_player_qty").value;

    //1. Calculate checked daily member type
    let selected_daily_member_type = document.querySelector('input[name="daily_member_type"]:checked');
    if( selected_daily_member_type != null){
        let daily_member_type_price = document.querySelector('input[name="daily_member_type"]:checked').getAttribute("data-type-price");
        //accum_court_booking_fees += parseInt(daily_member_type_price);
        acccum_daily_member_type_fees = parseInt(daily_member_type_price);
    }else{
        acccum_daily_member_type_fees = 0;
    }

    //console.log("Daily member type: " + acccum_daily_member_type_fees);

    //2. Calculate coach option 
    let selected_coach_option = document.querySelector('#coach_option:checked');
    if( selected_coach_option != null){
        let coach_option_price = document.querySelector('#coach_option:checked').getAttribute("data-coaching-price");
        //accum_court_booking_fees += parseInt(coach_option_price);
        if( booking_type == 'non-member'){
            acccum_coach_option_fees = parseInt(coach_option_price*all_checked_court_time_length);
            
        }else{
            acccum_coach_option_fees = parseInt(coach_option_price);
        } 
    }else{
        acccum_coach_option_fees = 0;
    }

    //console.log("Coach option: " + acccum_coach_option_fees);

    //3. Calculate coaching extra player 
    
    if( extra_player_amount != null){
        
        let calc_extra_player = parseInt(extra_player_amount) * lsc_pricing().guestFee;

        if( booking_type == 'non-member'){
            acccum_extra_player_fees = parseInt(calc_extra_player*all_checked_court_time_length);
        }else{
            acccum_extra_player_fees = parseInt(calc_extra_player);
        }
    }else{
        acccum_extra_player_fees = 0;
    }

    //console.log("added extra player: " + acccum_extra_player_fees);

    //4. Calculate checked times
    let count_calc = 0;

    let count_waitlist = 0;
    for (let index = 0; index < all_checked_court_time.length; index++) {
        count_calc++; 
        let temp_court_time = all_checked_court_time[index].split("/");
        let temp_court = temp_court_time[0];
        let temp_time = temp_court_time[1];
        let temp_price = 0;
        
        
        if( temp_court == 'waitlist'){
            temp_price = parseInt(0);
            count_waitlist++;
        }else{
            temp_price = get_price_from_timeslot(temp_court, temp_time);
        }
        
        accum_court_booking_fees += parseInt(temp_price);
        
    }


    let all_calc_fees = 0;
    all_calc_fees = parseInt(acccum_daily_member_type_fees) + parseInt(acccum_coach_option_fees) + parseInt(acccum_extra_player_fees) + parseInt(accum_court_booking_fees);

    if( count_waitlist > 0 && (all_checked_court_time.length == count_waitlist ) ){
        all_calc_fees = 0;
    }
    

    //console.log("All calculate fees" + all_calc_fees);

    document.getElementById("total_fee").value = all_calc_fees;
    document.getElementById("SummaryBookingFee").innerHTML = formatNumber(all_calc_fees);
    if( document.getElementById("creditAmount") != null ){

        document.getElementById("creditAmount").innerHTML = formatNumber(all_calc_fees);
    }

    updateMemberCreditBookingState(all_calc_fees);
    
}



//WHEN SELECT COURT TIME 
document.querySelectorAll('input[name="court_timeslot[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {

        
        //1. Process court time data
        process_court_time_data();

        //2. calculate booking fees
        calculate_all_booking_fees();

        

    });
});


//WHEN SELECT DAILY MEMBER TYPE
document.querySelectorAll('input[name="daily_member_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        //let selected_member_type = this.value;

         //1. Process court time data
        process_court_time_data();

        //2. calculate booking fees
        calculate_all_booking_fees();
    });
});


//WHEN SELECT COACHING OPTION
document.querySelectorAll('input[name="coach[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        let selected_coach = this.value;
        let member_type = document.getElementById("member_type").value;
        let booking_type = document.getElementById("booking_type").value;

         //1. Process court time data
        process_court_time_data();

        //2. calculate booking fees
        calculate_all_booking_fees();

        //3. Show Extra Player option
        if( ( selected_coach != '' &&  member_type == 'non-member' ) || ( selected_coach != '' &&  member_type == 'admin' && booking_type == 'non-member' )  ){
            document.getElementById("extraPlayer").classList.add("active");
        }else{
            document.getElementById("extraPlayer").classList.remove("active");
        }

    });
});

//WHEN SELECT EXTRA PLAYER
document.querySelectorAll('input[name="extra_player[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        let selected_extra_player = this.value;
        let extra_player_qty = document.getElementById("extra_player_qty").value;

        //1. Check if extra_player_qty is more than 0, if yes, set value to 1 
        if( selected_extra_player != ''){
            document.getElementById("extra_player_qty").value = 1;
        }else{
            document.getElementById("extra_player_qty").value = 0;
        }

        //2. calculate booking fees
        calculate_all_booking_fees();

    });
});


//WHEN ADD OR REMOVE EXTRA PLAYER

function minus_extra_player(Id){
    let currentVal = document.getElementById(Id).value;
    if( currentVal == 0){
        document.getElementById(Id).value = 0;
    }else{
        document.getElementById(Id).value = parseInt(currentVal) - 1;
    }
    calculate_all_booking_fees();
}

function plus_extra_player(Id){
    let currentVal = document.getElementById(Id).value;
    
    document.getElementById(Id).value = parseInt(currentVal) + 1;

    calculate_all_booking_fees();
}


//WHEN SELECT PAYMENT OPTION
document.querySelectorAll('input[name="payment_option"]').forEach(radio => {
    radio.addEventListener('change', function() {
        let selected_payment_option = radio.value;
        let member_current_credit = document.getElementById("member_current_credit").value;
        let total_fee = document.getElementById("total_fee").value;

        if( member_current_credit < total_fee){

        }
    });
});





$(document).ready(function () {

    function formatDate(inputDate) {
        if (!inputDate) {
            return '';
        }

        let dateParts = String(inputDate).split("-");
        if (dateParts.length === 3) {
            let year = parseInt(dateParts[0], 10);
            let month = parseInt(dateParts[1], 10) - 1;
            let day = parseInt(dateParts[2], 10);
            let dateObj = new Date(year, month, day);
            let options = { year: 'numeric', month: 'long', day: 'numeric' };
            return dateObj.toLocaleDateString('en-US', options);
        }

        let fallbackDate = new Date(inputDate);
        if (Number.isNaN(fallbackDate.getTime())) {
            return inputDate;
        }

        return fallbackDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    function isMobileBookingDevice() {
        return window.matchMedia("(max-width: 991px), (pointer: coarse)").matches;
    }

    function validateNonAdminBookingDate() {
        const bookingDateField = document.getElementById("date");
        const adminViewValue = $("#admin_view").val();
        if (!bookingDateField || adminViewValue === "true") {
            return true;
        }

        const selectedDate = bookingDateField.value;
        const minDate = bookingDateField.getAttribute("data-booking-min-date") || bookingDateField.min;
        const maxDate = bookingDateField.getAttribute("data-booking-max-date") || bookingDateField.max;

        if (!selectedDate) {
            return true;
        }

        if (maxDate && selectedDate > maxDate) {
            bookingDateField.value = maxDate;
            $("#SummaryDate").html(formatDate(maxDate));
            alert("Bookings are limited to today through 7 days in advance. The date has been changed to " + formatDate(maxDate) + ".");
            refreshAvailabilityForSelectedDate();
            return false;
        }

        if (minDate && selectedDate < minDate) {
            bookingDateField.value = minDate;
            $("#SummaryDate").html(formatDate(minDate));
            alert("Bookings cannot be made before " + formatDate(minDate) + ".");
            refreshAvailabilityForSelectedDate();
            return false;
        }

        return true;
    }

    //Foundation variables
    let admin_view_cond = $("#admin_view").val();
    let member_type = $("#member_type").val();

    const baseDisabledConfig = [
        {
            from: "2026-04-13",
            to:   "2026-04-15"
        }
    ];

    //Booking date 
    flatpickr("#all_booking_date", {
        dateFormat: "Y-m-d", // Format: YYYY-MM-DD
        defaultDate: "today",
        altInput: true,     // Show formatted date
        altFormat: "F j, Y", // Format: Full month name, day, year
        disable: baseDisabledConfig.slice(), // copy so we don't mutate original
        onChange: function(selectedDates, dateStr, instance) {

            let member_type = $("#member_type").val();

            $.post("view_bookings_by_date.php", { date: dateStr, member_type: member_type}, function (data) {
                $("#allBookingTableBody").html(data);
            });
        }
    });

    //Admin booking date 
    flatpickr("#admin-booking-date", {
        dateFormat: "Y-m-d", // Format: YYYY-MM-DD
        defaultDate: "today",
        altInput: true,     // Show formatted date
        altFormat: "F j, Y", // Format: Full month name, day, year
        //disable: baseDisabledConfig.slice(), // copy so we don't mutate original
        onChange: function(selectedDates, dateStr, instance) {
            $.post("view_bookings_by_date.php", { date: dateStr, member_type: member_type}, function (data) {
                $("#adminBookingTimeTable").html(data);
            });
        }
    });

    const bookingDateInput = document.getElementById("date");
    if (bookingDateInput) {
        const bookingMinDate = bookingDateInput.getAttribute("data-booking-min-date") || "today";
        const bookingMaxDate = bookingDateInput.getAttribute("data-booking-max-date") || new Date().fp_incr(7);
        if (admin_view_cond !== "true") {
            if (isMobileBookingDevice()) {
                bookingDateInput.type = "date";
                bookingDateInput.min = bookingMinDate;
                bookingDateInput.max = bookingMaxDate;
                bookingDateInput.addEventListener("change", function () {
                    if (validateNonAdminBookingDate()) {
                        $("#SummaryDate").html(formatDate(this.value));
                        refreshAvailabilityForSelectedDate();
                    }
                });
            } else {
                bookingDateInput.type = "text";
                flatpickr("#date", {
                    dateFormat: "Y-m-d",
                    defaultDate: bookingDateInput.value || "today",
                    minDate: bookingMinDate,
                    maxDate: bookingMaxDate,
                    altInput: true,
                    altFormat: "F j, Y",
                    disableMobile: true,
                    allowInput: false,
                    clickOpens: true,
                    disable: baseDisabledConfig.slice(),
                    onChange: function(selectedDates, dateStr, instance) {
                        if (validateNonAdminBookingDate()) {
                            $("#SummaryDate").html(formatDate(dateStr));
                            refreshAvailabilityForSelectedDate();
                        }
                    }
                });
            }
        } else {
            flatpickr("#date", {
                dateFormat: "Y-m-d",
                defaultDate: bookingDateInput.value || "today",
                altInput: true,
                altFormat: "F j, Y",
                disableMobile: true,
                allowInput: false,
                clickOpens: true,
                onChange: function(selectedDates, dateStr, instance) {
                    $("#SummaryDate").html(formatDate(dateStr));
                    refreshAvailabilityForSelectedDate();
                }
            });
        }
    }
    
    




    $("#checkAvailability").click(function () {
        //let court = $("#court").val();
        let date = $("#date").val();
        let admin_view = $("#admin_view").val();
        //console.log(admin_view);

        $(".loader-overlay").addClass("display");
 
        if (!date) {
            alert("Please select date.");
            return;
        }

        if (admin_view !== 'true' && !validateNonAdminBookingDate()) {
            $(".loader-overlay").removeClass("display");
            return false;
        }
        /*
        $.post("check_availability.php", { date: date, member_type: member_type, admin_view: admin_view }, function (data) {
            $("#largeTimeTable").html(data);
            refreshBookingRules();
        });
        */

        let availabilityPayload = getAvailabilityRequestPayload(date);
        let formData = new FormData();
            formData.append("date", availabilityPayload.date);
            formData.append("member_type", availabilityPayload.member_type);
            formData.append("admin_view", availabilityPayload.admin_view);
            formData.append("member_id", availabilityPayload.member_id);
            formData.append("limit_member_type", availabilityPayload.limit_member_type);

        $.ajax({
            url: "check_availability.php",
            type: "POST",
            data: formData,
            processData: false, // Prevent jQuery from processing data
            contentType: false, // Prevent jQuery from setting content type
            success: function(response) {
                $("#largeTimeTable").html(response);
                refreshBookingRules();
                $(".loader-overlay").removeClass("display");
                //alert(response);
                //$(".loader-overlay .inner-loader").html(response);
            }
            
        });
    });

    //Prepare form data for non-member
    function prepare_data_booking_non_member(){

        let booking_type = $("#booking_type").val();
        let date = $("#date").val();
        let member_id = $("#member_id").val();
        let member_name = $("#member_name").val();
        let non_member_email = $("#non_member_email").val();
        let non_member_phone = $("#non_member_phone").val();
        let non_member_info = member_name + "," + non_member_email + "," + non_member_phone;
        let daily_member_type = $("input[name='daily_member_type']:checked").val();
        let coach_option = $("#coach_option:checked").val();
        if( coach_option == null ){
            coach_option = '';
        }
        let extra_player_qty = $("#extra_player_qty").val();
        let extra_player = extra_player_qty;

        let all_selected_courts = $("#all_selected_courts").val();
        let all_selected_times = $("#all_selected_times").val();
        let transaction_amount = $("#total_fee").val();
        let payment_type = $("input[name='payment_option']:checked").val();
        let slip_upload = $("#slip_upload")[0].files[0];

        //Prepare formData
        let formData = new FormData();
        formData.append("booking_type", booking_type);
        formData.append("date", date);
        formData.append("member_id", member_id);
        formData.append("non_member_info", non_member_info);
        formData.append("daily_member_type", daily_member_type);
        formData.append("coach_option", coach_option);
        formData.append("extra_player", extra_player);
        formData.append("all_selected_courts", all_selected_courts);
        formData.append("all_selected_times", all_selected_times);
        formData.append("transaction_amount", transaction_amount);
        formData.append("payment_type", payment_type);
        if (slip_upload) {
            formData.append("slip_upload", slip_upload);
        }

        return formData;

    }

    //[ADMIN] Prepare form data for non-member
    function prepare_data_booking_admin_non_member(){

        let booking_type = $("#booking_type").val();
        let date = $("#date").val();
        let non_member_name = $("#non_member_name").val();
        let daily_member_type = $("input[name='daily_member_type']:checked").val();
        let coach_option = $("#coach_option:checked").val();
        if( coach_option == null ){
            coach_option = '';
        }
        let extra_player_qty = $("#extra_player_qty").val();
        let extra_player = extra_player_qty;
        let coach_name = $("#coach_name").val();
        let booking_note = $("#booking_note").val();

        let all_selected_courts = $("#all_selected_courts").val();
        let all_selected_times = $("#all_selected_times").val();
        let transaction_amount = $("#total_fee").val();
        let payment_type = $("input[name='payment_option']:checked").val();
        let not_paid_yet = $("input#not_paid_yet:checked").val();
        let slip_upload = $("#slip_upload")[0].files[0];

        //Prepare formData
        let formData = new FormData();
        formData.append("booking_type", booking_type);
        formData.append("date", date);
        formData.append("non_member_name", non_member_name);
        formData.append("daily_member_type", daily_member_type);
        formData.append("coach_option", coach_option);
        formData.append("extra_player", extra_player);
        formData.append("coach_name", coach_name);
        formData.append("booking_note", booking_note);
        formData.append("all_selected_courts", all_selected_courts);
        formData.append("all_selected_times", all_selected_times);
        formData.append("transaction_amount", transaction_amount);
        formData.append("payment_type", payment_type);
        formData.append("not_paid_yet", not_paid_yet);
        if( not_paid_yet == undefined){
            not_paid_yet = '';
        }
        if (slip_upload) {
            formData.append("slip_upload", slip_upload);
        }


        return formData;

    }

    //Prepare form data for member
    function prepare_data_booking_member(){

        let booking_type = $("#booking_type").val();
        let date = $("#date").val();
        let member_id = $("#member_id").val();
        let daily_member_type = $("#member_type").val();
        /*let coach_option = $("#coach_option:checked").val();
        if( coach_option == null ){
            coach_option = '';
        }
        */
        let extra_player_qty = $("#extra_player_qty").val();
        let extra_player = extra_player_qty;

        let coach_name = $("#coach_name").val();

        let all_selected_courts = $("#all_selected_courts").val();
        let all_selected_times = $("#all_selected_times").val();
        let transaction_amount = $("#total_fee").val();
        let payment_type = $("input[name='payment_option']:checked").val();
        let credit = $("#current_credit").val();
        let slip_upload = $("#slip_upload")[0].files[0];

        //Prepare formData
        let formData = new FormData();
        formData.append("booking_type", booking_type);
        formData.append("date", date);
        formData.append("member_id", member_id);
        formData.append("daily_member_type", daily_member_type);
        //formData.append("coach_option", coach_option);
        formData.append("extra_player", extra_player);
        formData.append("coach_name", coach_name);
        formData.append("all_selected_courts", all_selected_courts);
        formData.append("all_selected_times", all_selected_times);
        formData.append("transaction_amount", transaction_amount);
        formData.append("payment_type", payment_type);
        formData.append("credit", credit);
        if (slip_upload) {
            formData.append("slip_upload", slip_upload);
        }

        return formData;

    }

    //[Admin] Prepare form data for member
    function prepare_data_booking_admin_member(){

        let booking_type = $("#booking_type").val();
        let date = $("#date").val();
        let member_id = $("#member_id").val();
        let daily_member_type = $("#member_type").val();
        let coach_option = $("#coach_option:checked").val();
        if( coach_option == null ){
            coach_option = '';
        }
        let extra_player_qty = $("#extra_player_qty").val();
        let extra_player = extra_player_qty;
        let coach_name = $("#coach_name").val();
        let booking_note = $("#booking_note").val();

        let all_selected_courts = $("#all_selected_courts").val();
        let all_selected_times = $("#all_selected_times").val();
        let transaction_amount = $("#total_fee").val();
        let payment_type = $("input[name='payment_option']:checked").val();
        let credit = $("#current_credit").val();
        let not_paid_yet = $("input#not_paid_yet:checked").val();
        let slip_upload = $("#slip_upload")[0].files[0];
        if( not_paid_yet == undefined){
            not_paid_yet = '';
        }

        //Prepare formData
        let formData = new FormData();
        formData.append("booking_type", booking_type);
        formData.append("date", date);
        formData.append("member_id", member_id);
        formData.append("daily_member_type", daily_member_type);
        formData.append("coach_option", coach_option);
        formData.append("extra_player", extra_player);
        formData.append("coach_name", coach_name);
        formData.append("booking_note", booking_note);
        formData.append("all_selected_courts", all_selected_courts);
        formData.append("all_selected_times", all_selected_times);
        formData.append("transaction_amount", transaction_amount);
        formData.append("payment_type", payment_type);
        formData.append("credit", credit);
        formData.append("not_paid_yet", not_paid_yet);
        if (slip_upload) {
            formData.append("slip_upload", slip_upload);
        }

        return formData;

    }


    //Prepare form data for admin - academy
    function prepare_data_booking_academy(){

        let booking_type = $("#booking_type").val();
        let date = $("#date").val();
        let member_id = $("#member_id").val();

        let all_selected_courts = $("#all_selected_courts").val();
        let all_selected_times = $("#all_selected_times").val();

        //Prepare formData
        let formData = new FormData();
        formData.append("booking_type", booking_type);
        formData.append("date", date);
        formData.append("member_id", member_id);
        formData.append("all_selected_courts", all_selected_courts);
        formData.append("all_selected_times", all_selected_times);

        return formData;

    }


    function ajax_make_booking(formData, booking_type, admin_view){

        let booking_action = '';

        if( booking_type == 'non-member' && admin_view == 'false'){

            booking_action = 'book-non-member.php';

        }else if( booking_type == 'member booking' &&  admin_view == 'false'){

            booking_action = 'book-member.php';

        }else if( booking_type == 'non-member' &&  admin_view == 'true'){

            booking_action = 'book-admin-non-member.php';
            console.log("Admin non member booking ajax");
            

        }else if( booking_type == 'member booking' &&  admin_view == 'true'){

            booking_action = 'book-admin-member.php';
            console.log("Admin member booking ajax");

        }else if( ( booking_type == 'junior_academy' || booking_type == 'adult_clinic' || booking_type == 'tennis_camp' || booking_type == 'tournament' ) &&  admin_view == 'true' ){

            booking_action = 'book-academy.php';

        }else{ }

        
        $(".loader-overlay").addClass("display");
        $.ajax({
            url: booking_action,
            type: "POST",
            data: formData,
            processData: false, // Prevent jQuery from processing data
            contentType: false, // Prevent jQuery from setting content type
            success: function(response) {
                //alert(response);
                $(".loader-overlay .inner-loader").html(response);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                console.log(xhr.status);
                console.log(xhr.responseText);
                console.log(thrownError);
            }
            
        });

    }

    //Make booking
    $("#bookButton").click(function(){
        let admin_view = $("#admin_view").val();
        let booking_type = $("#booking_type").val();
        let all_selected_courts = $("#all_selected_courts").val();
        let all_selected_times = $("#all_selected_times").val();
        let payment_type = $("input[name='payment_option']:checked").val();
        let total_fee = parseInt($("#total_fee").val() || "0", 10);
        let member_current_credit = parseInt($("#member_current_credit").val() || "0", 10);

        console.log("payment_type: " + payment_type)

        if( payment_type == '' || payment_type == undefined){
            alert("Please select payment option");
            return false;
        }

        //VALIDATE 
        if( all_selected_courts == '' && all_selected_times == '' ){
            alert("Please select court and time");
            return false;
        }

        if( admin_view != 'true' && !validateNonAdminBookingDate() ){
            return false;
        }

        if( booking_type == 'member booking' && payment_type == 'credit' && member_current_credit < total_fee ){
            alert("You do not have enough credit for this booking. Please refill credit before booking.");
            return false;
        }

        //ACTION BOOKING
        if( admin_view == 'false' &&  booking_type == 'member booking'){
            //member booking

            if( payment_type == 'qr'){

                $('#bankTransfer').modal('show');

            }else{

                let formData = prepare_data_booking_member();
                ajax_make_booking(formData, booking_type, admin_view);

            }

        }else if( admin_view == 'false' &&  booking_type == 'non-member' ){
            //non-member booking

            if( payment_type == 'Pay later'){

                let formData = prepare_data_booking_non_member();
                ajax_make_booking(formData, booking_type, admin_view);

            }else{
                $('#bankTransfer').modal('show');
            }

        }if( admin_view == 'true' &&  booking_type == 'member booking'){
            //[ADMIN] member booking

            if( $("#admin_select_member").val() == ''){
                alert("Please select a member");

                return false;
            }

            if( payment_type == 'qr'){

                $('#bankTransfer').modal('show');

            }else{

                let formData = prepare_data_booking_admin_member();
                ajax_make_booking(formData, booking_type, admin_view);

            }
            

        }else if( admin_view == 'true' &&  booking_type == 'non-member' ){
            //[ADMIN] non-member booking
            let non_member_name = $("#non_member_name").val();
            if( non_member_name == ''){
                alert("Please enter non member name");

                return false;
            }

            if( payment_type == 'qr'){

                $('#bankTransfer').modal('show');

            }else{

                let formData = prepare_data_booking_admin_non_member();
                ajax_make_booking(formData, booking_type, admin_view);

            }

        }else{ }

    });

    $("#ConfirmBankTransferButton").on("click", function () {
        let admin_view = $("#admin_view").val();
        let booking_type = $("#booking_type").val();
        let slip_upload = $("#slip_upload")[0].files[0];

        if( admin_view != 'true' && !validateNonAdminBookingDate() ){
            return false;
        }

        if( slip_upload == undefined){
            alert("Please upload slip after you make payment");
            return false;
        }

        $(".loader-overlay").addClass("display");
        let formData = prepare_data_booking_member();
        //ACTION BOOKING
        if( admin_view == 'false' &&  booking_type == 'member booking'){
            //member booking
            console.log("Bank member booking");
            formData = prepare_data_booking_member();

        }else if( admin_view == 'false' &&  booking_type == 'non-member' ){
            //non-member booking
            console.log("Bank non-member booking");
            formData = prepare_data_booking_non_member();

        }else if( admin_view == 'true' &&  booking_type == 'member booking'){
            //[ADMIN] member booking
            console.log("Bank Admin member booking");
            formData = prepare_data_booking_admin_member();

        }else if( admin_view == 'true' &&  booking_type == 'non-member' ){
            //[ADMIN] non-member booking
            console.log("Bank Admin non-member booking");
            formData = prepare_data_booking_admin_non_member();

            /*for (var pair of formData.entries()) {
                console.log(pair[0]+ ', ' + pair[1]); 
            }*/

        }else{ }

        ajax_make_booking(formData, booking_type, admin_view);



    });

    //Admin book button
    $("#adminBookButton").click(function(){
        let admin_view = $("#admin_view").val();
        let booking_type = $("#booking_type").val();
        let all_selected_courts = $("#all_selected_courts").val();
        let all_selected_times = $("#all_selected_times").val();

        //VALIDATE 
        if( all_selected_courts == '' && all_selected_times == '' ){
            alert("Please select court and time");
            return false;
        }else{

            let formData = prepare_data_booking_academy();
            ajax_make_booking(formData, booking_type, admin_view);

        }

    });



    $("#searchMember").click(function () {
        let member_detail = $("#member_detail").val();
        let search_type = $("#search_type").val();
        let search_member_params = $("#search_member_params").val();
        if( !search_member_params ){
            search_member_params = '';
        }

        console.log("member type search : " + search_member_params);

        if (!member_detail) {
            alert("Please enter member number or name");
            return;
        }

        // search member 
        $.ajax({
            url: "admin-search-member.php",
            type: "POST",
            data: { 
                member_detail: member_detail,
                search_type: search_type,
                search_member_params: search_member_params
            },
            success: function (response) {
                $("#memberResultList").html(response);
            }
        });
    });


    $("#searchMemberCredit").click(function () {
        let member_detail = $("#member_detail").val();
        let search_type = $("#search_type").val();
        if (!member_detail) {
            alert("Please enter member number or name");
            return;
        }

        // search member 
        $.ajax({
            url: "admin-search-member.php",
            type: "POST",
            data: { 
                member_detail: member_detail,
                search_type: search_type,
                search_for: "add_credit"
            },
            success: function (response) {
                $("#memberResultList").html(response);
            }
        });
    });



    $("#viewCreditConditions").click(function(){

        $('#modalCreditPaymentConditions').modal('show');

    });




});
