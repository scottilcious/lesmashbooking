<?php require_once __DIR__ . '/../includes/pricing.php'; ?>
<?php if ( $memberType != 'non-member'  ) : ?>
<div class="additional-players">
    <div class="coach-wrapper mt-1 coach-extra-player-wrapper active" id="extraPlayer">
        <label for="extra_player_qty">Amount of guests <br>(Guest fee : +<?= LSC_GUEST_FEE ?>THB/person)<br></label>
            <div class="row">
                <div class="col-12 col-md-3">
                <div class="input-group input-group-lg">
                    <button type="button" id="extra_player_qty_minus" 
                    class="btn btn-outline-secondary" onclick="minus_extra_player('extra_player_qty')">
                        -</button>
                    <input type="number" class="form-control" name="extra_player_qty" id="extra_player_qty" aria-describedby="AmountExtraPlayer" min="0" value="0">
                    <button type="button" id="extra_player_qty_plus" 
                    class="btn btn-outline-secondary"  onclick="plus_extra_player('extra_player_qty')">
                        +</button>
                </div>
                </div>
            </div>
    </div>
</div>
<?php endif; ?>


<?php if ( $memberType == 'non-member'  ) : ?>
<div class="member-type-wrapper mt-5 mb-5">
    <p class="fs-4 mb-0">Select Coaching Option</p>
    
    <div class="card">
        <div class="card-body">
            <div class="coach-wrapper">
                <label for="coach_option">
                    <input type="checkbox" name="coach[]" id="coach_option" value="Assistant coach" data-coaching-price="<?= LSC_COACH_FEE ?>">
                    Assistant coach (<?= LSC_COACH_FEE ?>THB)
                </label>
            </div>
            <div class="coach-wrapper mt-1 coach-extra-player-wrapper" id="extraPlayer">
                <label for="extra_player" class="mt-3">
                    <input type="checkbox" name="extra_player[]" id="extra_player" value="Extra player">
                    Extra Player (+<?= LSC_GUEST_FEE ?>THB/person)
                </label>
                <div class="row">
                    <div class="col-6 col-md-3">
                        <label for="extra_player_qty">Amount of extra players</label>
                        <div class="input-group input-group-lg">
                            <button type="button" id="extra_player_qty_minus" 
                            class="btn btn-outline-secondary" onclick="minus_extra_player('extra_player_qty')">
                                -</button>
                            <input type="number" class="form-control" name="extra_player_qty" id="extra_player_qty" aria-describedby="AmountExtraPlayer" min="0" value="0">
                            <button type="button" id="extra_player_qty_plus" 
                            class="btn btn-outline-secondary"  onclick="plus_extra_player('extra_player_qty')">
                                +</button>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    

    <div class="alert alert-warning mt-2" role="alert">
        Request a specific coach, please contact the reception  
    </div>

    <?php if ( $memberType == 'admin') : ?>
    <div class="coach-name mt-3">
        <label for="coach_name">Coach name</label>
        <input type="text" class="form-control" name="coach_name" id="coach_name">
    </div>

    <div class="booking-note mt-3">
        <label for="booking_note">Booking Note</label>
        <textarea name="booking_note" id="booking_note" class="form-control"></textarea>
    </div>
    <?php endif; ?>
</div>

<?php elseif( $memberType == 'admin' ): ?>

<div class="member-type-wrapper mt-5 mb-5">
    <input type="hidden" class="form-control" name="extra_player_qty" id="extra_player_qty" aria-describedby="AmountExtraPlayer" min="0" value="0">
    <div class="coach-name mt-3">
        <label for="coach_name">Coach name</label>
        <input type="text" class="form-control" name="coach_name" id="coach_name">
    </div>

    <div class="booking-note mt-3">
        <label for="booking_note">Booking Note</label>
        <textarea name="booking_note" id="booking_note" class="form-control"></textarea>
    </div>
</div>


<?php else: ?>
    <div class="member-type-wrapper mt-5 mb-5">
    <p class="fs-4 mb-0">Request a specific coach</p>
    <p>To request a specific coach, please contact us via WhatsApp or LINE.</p>
    <div class="row">
        <div class="col-auto">
             Whatsapp:<br>
            <img src="images/whatsapp.jpg" alt="" width="150">
        </div>
        <div class="col-auto">LINE ID: <a href="https://line.me/ti/p/~lesmashclub" target="_blank">lesmashclub</a><br>
            <img src="images/line.png" alt="" width="150">
        </div>
    </div>
        <div class="card" style="opacity: 0; height: 1; width: 1; overflow: hidden;">
            <div class="card-body">
                <div class="coach-name">
                    <label for="coach_name">Coach name</label>
                    <input type="text" class="form-control" name="coach_name" id="coach_name">
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
