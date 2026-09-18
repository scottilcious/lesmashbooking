<!-- Modal Bank Transfer -->
<div class="modal fade" id="bankTransfer" tabindex="-1" aria-labelledby="bankTransferLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="bankTransferLabel">Bank Transfer</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>You have selected bank transfer as payment option, please make payment via the following details:</p>
        
        <div class="alert alert-warning mt-3 text-center" role="alert">
          If you don't have a thai bank account, please contact the reception or contact us via line account<br>
          <a href="https://line.me/ti/p/lesmashclub" target="_blank"><img src="images/line_icon.png" alt="Line Account" width="24"></a>&nbsp;
          <a href="https://line.me/ti/p/lesmashclub" target="_blank">lesmashclub</a>
        </div>
        <div class="row">
          <div class="col-12 col-md-6">
            <small class="text-body-tertiary">Bank account details:</small>
            <div class="bank-detail mb-2">
            <div class="list-bank-detail pt-1 pb-1">
                Account name: <span class="fw-bold">MPM Asia</span>
            </div>
            <div class="list-bank-detail pt-1 pb-1">
              Account number: <span class="fw-bold">057 286 4863</span>
            </div>
            <div class="list-bank-detail pt-1 pb-1">
            Bank: <span class="fw-bold">Kasikorn Bank</span>
            </div>
        </div>
          </div>
          <div class="col-12 col-md-6 text-center">
            <small class="text-body-tertiary">Or scan QR code below to pay via Promptpay</small><br>
            <img src="images/lesmash_qr2.png" alt="" class="w-50">
          </div>
        </div>
        
        <hr>
        <div class="alert alert-primary" role="alert">
            After making payment, please upload the payment slip 
        </div>
        <input class="form-control form-control-lg" id="slip_upload" id="slip_upload" type="file">
        <div class="warning-payment-wrapper d-none">
            <div class="alert alert-danger" role="alert">
                Please upload slip after you make payment
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="ConfirmBankTransferButton">
            Confirm and make booking</button>
      </div>
    </div>
  </div>
</div>
