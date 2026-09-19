$(document).ready(function () {


});

// Keep a ref to the checkbox that triggered the modal so we can handle cancel.
let _waitlistTriggerCheckbox = null;

// Basic phone validator (tweak to your format needs)
function isValidPhone(p) {
  // Example: allow digits, spaces, +, -, (), min 8 chars
  return /^[0-9+()\-\s]{8,}$/.test(String(p).trim());
}

document.addEventListener('DOMContentLoaded', function () {
  // Bootstrap modal instance
  const modalCreditEl = document.getElementById('lowCreditModal');
  const modalWaitlistCreditLowAlert = document.getElementById('modalWaitlistCreditLowAlert');
  const modalWaitlistPhoneConfirmWrapper = document.getElementById('modalWaitlistPhoneConfirmWrapper');
  const modalWaitlistConfirmCTAs = document.getElementById('modalWaitlistConfirmCTAs');
  const modalCreditElModal = new bootstrap.Modal(modalCreditEl);
  const $memberCurrentCreditChk = $('#current_member_credit_chk');

  const modalEl = document.getElementById('waitlistPhoneModal');
  const waitlistModal = new bootstrap.Modal(modalEl);
  const $phoneInput = $('#waitlistPhoneInput');
  const $hiddenConfirmed = $('#member_phone_confirmed');
  const $userId = $('#waitlistUserId');
  const memoryConfirmedNumberBool = localStorage.getItem("ConfirmedNumberBool");

  // 1) Detect checking of any waitlist checkbox
  $(document).on('change', 'input[name="court_timeslot[]"]', function () {
    const val = String(this.value || '').toLowerCase();
    const isChecked = this.checked;

    const localUIDConfirm = $userId.val();

    // Check low credit & phone validation
    if( isChecked && val.includes('waitlist') &&  $memberCurrentCreditChk.val() < ((window.LSC_PRICING && window.LSC_PRICING.courtEvening) || 280) ){
      //modalCreditElModal.show();
      modalWaitlistCreditLowAlert.classList.remove('d-none');
      //modalWaitlistPhoneConfirmWrapper.classList.add('d-none');
      //modalWaitlistConfirmCTAs.classList.add('d-none');

      waitlistModal.show();

      /*
      modalCreditEl.addEventListener('hidden.bs.modal', function onHidden() {
        //console.log("dismissed credit");
        waitlistModal.show();
        // Remove event listener to prevent multiple triggers
        modalCreditEl.removeEventListener('hidden.bs.modal', onHidden);
      });
      */
      
    }else if( isChecked && val.includes('waitlist') && memoryConfirmedNumberBool != localUIDConfirm ){
        waitlistModal.show();
        modalWaitlistCreditLowAlert.classList.add('d-none');
        //modalWaitlistPhoneConfirmWrapper.classList.remove('d-none');
        //modalWaitlistConfirmCTAs.classList.remove('d-none');      
    }else{

    }

    // Check phone number
    /*
    if (isChecked && val.includes('waitlist') && memoryConfirmedNumberBool != localUIDConfirm ) {
      _waitlistTriggerCheckbox = this; // remember which one triggered
      // Prefill input from hidden (could already be edited previously)
      $phoneInput.val($hiddenConfirmed.val() || $phoneInput.val());
      waitlistModal.show();
    }
    */
  });

  // If modal is dismissed (esc/X/Cancel), uncheck the box that triggered it
  modalEl.addEventListener('hidden.bs.modal', function () {
    if (_waitlistTriggerCheckbox && _waitlistTriggerCheckbox.checked) {
      _waitlistTriggerCheckbox.checked = false;
    }
    _waitlistTriggerCheckbox = null;
  });

  // 2) Confirm (use as-is, no DB save)
  $('#waitlistConfirmBtn').on('click', function () {
    const phone = $phoneInput.val().trim();
    if (!isValidPhone(phone)) {
      alert('Please enter a valid phone number.');
      return;
    }
    // update hidden for form submit
    $hiddenConfirmed.val(phone);
    // keep the checkbox checked and close modal
    if (_waitlistTriggerCheckbox) {
      _waitlistTriggerCheckbox.checked = true;
    }
    // do not uncheck on hide (we handled it), so clear the ref first
    const tmp = _waitlistTriggerCheckbox;
    _waitlistTriggerCheckbox = null;
    // now hide
    const modal = bootstrap.Modal.getInstance(modalEl);
    modal.hide();
  });

  // 3) Save & Confirm (AJAX update members.member_phone)
  $('#waitlistSaveConfirmBtn').on('click', function () {
    const phone = $phoneInput.val().trim();
    if (!isValidPhone(phone)) {
      alert('Please enter a valid phone number.');
      return;
    }

    const userId = $userId.val();

    // Disable buttons during save
    $('#waitlistSaveConfirmBtn, #waitlistConfirmBtn, #waitlistCancelBtn').prop('disabled', true);

    $.ajax({
      url: 'booking_functions/update_phone.php',
      type: 'POST',
      dataType: 'json',
      data: { user_id: userId, member_phone: phone },
      success: function (resp) {
        if (resp && resp.success) {
          // set hidden for the form submit
          $hiddenConfirmed.val(phone);
          // keep checkbox checked and close modal
          if (_waitlistTriggerCheckbox) {
            _waitlistTriggerCheckbox.checked = true;
          }
          // clear ref and hide
          _waitlistTriggerCheckbox = null;
          const modal = bootstrap.Modal.getInstance(modalEl);
          modal.hide();

          //set local storage 
          localStorage.setItem("ConfirmedNumberBool", userId);
        } else {
          alert(resp && resp.message ? resp.message : 'Failed to save phone number.');
        }
      },
      error: function (xhr) {
        alert('Error saving phone number. Please try again.');
        console.error(xhr.responseText || xhr.statusText);
      },
      complete: function () {
        $('#waitlistSaveConfirmBtn, #waitlistConfirmBtn, #waitlistCancelBtn').prop('disabled', false);
      }
    });
  });
});



//Trigger modal if waitlist is is avaiable 
const waitlistCheckElem = document.getElementById("waitlist_check");
if (typeof(waitlistCheckElem) != 'undefined' && waitlistCheckElem != null){
  console.log('waitlist check exists');
  const waitlistCheckVal = waitlistCheckElem.value;

  if( waitlistCheckVal == 'waitlist_true'){
    const waitlistCheckElem = document.getElementById('waitlistNotificationModal');
    const waitlistCheckModal = new bootstrap.Modal(waitlistCheckElem);
    waitlistCheckModal.show();

  }
}
