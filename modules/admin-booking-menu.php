<div class="mb-3 pb-3 border-bottom <?= ($param_booking_type)? $param_booking_type : '' ?>">
        <h2 class="fs-5">Booking type</h2>
        <ul class="nav nav-pills" <?= $param_booking_type ?>>
            <li class="nav-item">
                <a class="nav-link <?= ($param_booking_type == '')? 'active' : '' ?>" 
                aria-current="page" href="index.php">Member booking</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($param_booking_type == 'non-member')? 'active' : '' ?>" 
                aria-current="page" href="index.php?param_booking_type=non-member">Non-member booking</a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link <?= ($param_booking_type == 'academy' || $param_booking_type == 'junior_academy' || $param_booking_type == 'adult_clinic' || $param_booking_type == 'tennis_camp' || $param_booking_type == 'tournament')? 'active' : '' ?> dropdown-toggle" data-bs-toggle="dropdown" href="#" 
                role="button" aria-expanded="false">
                <?php 
                    if($param_booking_type == 'junior_academy'):
                        echo 'Junior academy';
                    elseif( $param_booking_type == 'adult_clinic' ):
                        echo 'Adult clinic';
                    elseif( $param_booking_type == 'tennis_camp' ):
                        echo 'Tennis camp';
                    elseif( $param_booking_type == 'tournament' ):
                        echo 'Tournament';
                    else:
                        echo 'Academy booking';
                    endif;
                ?>
                </a>
                <ul class="dropdown-menu">
                    <!-- <li><a class="dropdown-item" href="admin-court-booking.php?param_booking_type=academy">
                        Academy booking</a></li> -->
                    <li><a class="dropdown-item" href="admin-court-booking.php?param_booking_type=junior_academy">
                        Junior academy</a></li>
                    <li><a class="dropdown-item" href="admin-court-booking.php?param_booking_type=adult_clinic">
                        Adult clinic</a></li>
                    <li><a class="dropdown-item" href="admin-court-booking.php?param_booking_type=tennis_camp">
                        Tennis camp</a></li>
                    <li><a class="dropdown-item" href="admin-court-booking.php?param_booking_type=tournament">
                        Tournament</a></li>
                </ul>
            </li>
        </ul>
</div>