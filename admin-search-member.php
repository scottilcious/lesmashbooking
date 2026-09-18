<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();
require 'config.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $member_detail = $_POST['member_detail'];
    $search_type = $_POST['search_type'];
    $search_for = $_POST['search_for'];
    $search_member_params = $_POST['search_member_params'];

    try {

        if( $search_type == "member_name" && $search_member_params == ''){

            $member_detail = "%$member_detail%";
            $stmt = $pdo->prepare("SELECT * FROM members WHERE first_name LIKE ?"); 
            $stmt->execute([$member_detail]);
            
        }else if( $search_type == "member_number" && $search_member_params == '' ){

            $stmt = $pdo->prepare("SELECT * FROM members WHERE member_number = ?");
            $stmt->execute([$member_detail]);

        }else if( $search_type == "member_name" && $search_member_params == 'non-member'){

            $member_detail = "%$member_detail%";
            $stmt = $pdo->prepare("SELECT * FROM members WHERE first_name LIKE ? AND member_type = 'non-member'"); 
            $stmt->execute([$member_detail]);
            
        }else if( $search_type == "member_number" && $search_member_params == 'non-member' ){

            $stmt = $pdo->prepare("SELECT * FROM members WHERE member_number = ? AND member_type = 'non-member'");
            $stmt->execute([$member_detail]);

        }else if( $search_type == "member_name" && $search_member_params == 'member'){

            $member_detail = "%$member_detail%";
            $stmt = $pdo->prepare("SELECT * FROM members WHERE first_name LIKE ? AND NOT member_type = 'non-member'"); 
            $stmt->execute([$member_detail]);
            
        }else if( $search_type == "member_number" && $search_member_params == 'member' ){

            $stmt = $pdo->prepare("SELECT * FROM members WHERE member_number = ? AND NOT member_type = 'non-member'");
            $stmt->execute([$member_detail]);

        }else{

            $member_detail = "%$member_detail%";
            $stmt = $pdo->prepare("SELECT * FROM members WHERE first_name LIKE ?");
            $stmt->execute([$member_detail]);

        }

        //$members = $stmt->fetch(PDO::FETCH_ASSOC);
        $members = $stmt->fetchAll();
        ?>
        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Member number</th>
                    <th>Member name</th>
                    <th>Member type</th>
                    <th>Member status</th>
                    <th>Remaining credit</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $member_count = 1;
                
                foreach ($members as $member): 

                $expirationDate = new DateTime($member['member_expiration']);
                $today = new DateTime('today'); // Sets time to 00:00:00 for accurate date comparison

                $member_expired = false;

                if ($expirationDate < $today) {
                    $member_expired = true;
                } else {
                    $member_expired = false;
                }

                    ?>
                <tr id="member-<?= $member['id'] ?>">
                    <td class="<?= ($member_expired == true) ? 'bg-warning-subtle' : '' ?>"><?= $member_count ?></td>
                    <td class="<?= ($member_expired == true) ? 'bg-warning-subtle' : '' ?>"><?= $member['member_number'] ?></td>
                    <td class="<?= ($member_expired == true) ? 'bg-warning-subtle' : '' ?>"><?= $member['first_name'] ?> <?= $member['last_name'] ?></td>
                    <td class="<?= ($member_expired == true) ? 'bg-warning-subtle' : '' ?>"><span class="badge text-bg-secondary"><?= $member['member_type'] ?></span></td>
                    <td class="<?= ($member_expired == true) ? 'bg-warning-subtle' : '' ?>">
                        <?php if( $member_expired == true){ ?>

                        <span class="badge bg-warning">
                            Expired
                        </span>

                        <?php }else{ ?>
                        <span class="badge <?= $member['member_status'] == 'inactive' ? 'bg-danger' : 'bg-success' ?>">
                            <?= htmlspecialchars($member['member_status']) ?>
                        </span>

                        <?php } ?>
                    </td>
                    <td class="<?= ($member_expired == true) ? 'bg-warning-subtle' : '' ?>"><?= $member['credit'] ?></td>
                    <td class="<?= ($member_expired == true) ? 'bg-warning-subtle' : '' ?>">
                        <?php if( $search_for == 'add_credit'): ?>
                            <button type="button"  class="btn btn-outline-success"
                            onclick="select_member_credit(this)"
                            data-member-id="<?= $member['id'] ?>"
                            data-member-name="<?= $member['first_name'] ?> <?= $member['last_name'] ?>"
                            data-member-number="<?= $member['member_number'] ?>"
                            >
                                <i class="ri-check-line"></i> Select
                            </button>
                        <?php else: 
                            $member_current_credit = number_format($member['credit'],0,'', '');
                            ?>
                            <?php if( $member_expired == true){ ?>
                                <span class="badge bg-warning">Expired Member</span>
                            <?php }else{ ?>
                            <button type="button" class="btn btn-outline-success btn-select-member" 
                        id="member_<?= $member['id'] ?>"
                        data-member-id="<?= $member['id'] ?>"
                        data-member-type="<?= $member['member_type'] ?>"
                        data-member-first-name="<?= $member['first_name'] ?>"
                        data-member-last-name="<?= $member['last_name'] ?>"
                        data-member-number="<?= $member['member_number'] ?>"
                        data-member-credit="<?= $member_current_credit ?>"
                        
                        >
                        <?php 
                        /*
                        <!-- onclick="select_member('<?= $member['id'] ?>','<?= $member['member_type'] ?>', '<?= $member['first_name'] ?>', '<?= $member['last_name'] ?>', '<?= $member['member_number'] ?>', '<?= $member_current_credit ?>')" -->
                        */
                        ?>


                            <i class="ri-check-line"></i> Select
                        </button>
                            <?php } ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
                $member_count++;
                endforeach; ?>
            </tbody>
        </table>

        <script>
            $(document).ready(function(){

                $('.btn-select-member').click(function(){
                    let member_id = $(this).data('member-id');
                    let member_type = $(this).data('member-type');
                    let member_first_name = $(this).data('member-first-name');
                    let member_last_name = $(this).data('member-last-name');
                    let member_number = $(this).data('member-number');
                    let member_credit = $(this).data('member-credit');

                    if( member_credit == null || member_credit == undefined || member_credit == ''){
                        member_credit = 0;
                    }
                    
                    document.getElementById("member_id").value = member_id;
                    document.getElementById("member_type").value = member_type;
                    if( member_type != 'non-member'){
                        document.getElementById("daily_member_type_input").value = member_type;
                    }

                    document.getElementById("admin_select_member").value = "true";
                    
                    document.getElementById("memberSearchDetail").classList.add("display");
                    document.getElementById("memberName").innerHTML = member_first_name + " " + member_last_name;
                    document.getElementById("memberNumber").innerHTML = member_number;
                    document.getElementById("current_credit").value = member_credit;
                    document.getElementById("member_current_credit").value = member_credit;

                    if( member_credit > 0 ){
                        document.getElementById("payment_option_credit").disabled = false;
                        document.getElementById("payment_option_credit").checked = true;
                    }else{
                        document.getElementById("payment_option_credit").checked = false;
                        document.getElementById("payment_option_credit").disabled = true;
                    }

                    document.getElementById("remaining_credit_payment_type").innerHTML = member_credit;
                    document.getElementById("creditWarning").classList.add("d-none");
                    if (typeof refreshBookingRules === 'function') {
                        refreshBookingRules();
                    }
                    if (typeof refreshAvailabilityForSelectedDate === 'function') {
                        refreshAvailabilityForSelectedDate();
                    }
                    if (typeof calculate_all_booking_fees === 'function') {
                        calculate_all_booking_fees();
                    }


                    bootstrap.Modal.getInstance(document.getElementById('memberSearchResults')).hide();

                    

                });

            });

            /*
            function select_searched_member(){

            }
            */

            function select_member_credit(elem){
                let member_id = elem.getAttribute("data-member-id");
                let member_name = elem.getAttribute("data-member-name");
                let member_number = elem.getAttribute("data-member-number");

                console.log(member_id);
                console.log(member_name);
                console.log(member_number);

                document.getElementById("memberSearchDetail").classList.add("display");
                document.getElementById("memberName").innerHTML = member_name;
                document.getElementById("memberNumber").innerHTML = member_number;
                document.getElementById("member_id").value = member_id;

                bootstrap.Modal.getInstance(document.getElementById('memberSearchResults')).hide();

            }

            function select_member(member_id, member_type, first_name, last_name, member_number, credit){

                if( credit == null || credit == undefined || credit == ''){
                    credit = 0;
                }
                
                document.getElementById("member_id").value = member_id;
                document.getElementById("member_type").value = member_type;
                if( member_type != 'non-member'){
                    document.getElementById("daily_member_type_input").value = member_type;
                }

                document.getElementById("admin_select_member").value = "true";
                
                document.getElementById("memberSearchDetail").classList.add("display");
                document.getElementById("memberName").innerHTML = first_name + " " + last_name;
                document.getElementById("memberNumber").innerHTML = member_number;
                document.getElementById("current_credit").value = credit;
                document.getElementById("member_current_credit").value = credit;

                if( credit > 0 ){
                    document.getElementById("payment_option_credit").disabled = false;
                }else{
                    document.getElementById("payment_option_credit").checked = false;
                    document.getElementById("payment_option_credit").disabled = true;
                }

                document.getElementById("remaining_credit_payment_type").innerHTML = credit;
                document.getElementById("creditWarning").classList.add("d-none");
                if (typeof refreshBookingRules === 'function') {
                    refreshBookingRules();
                }
                if (typeof refreshAvailabilityForSelectedDate === 'function') {
                    refreshAvailabilityForSelectedDate();
                }
                if (typeof calculate_all_booking_fees === 'function') {
                    calculate_all_booking_fees();
                }


                bootstrap.Modal.getInstance(document.getElementById('memberSearchResults')).hide();
            }
        </script>

        <?php 

    } catch (PDOException $e) {
        echo $e->getMessage();
    }


?>




<?php 
}
?>
