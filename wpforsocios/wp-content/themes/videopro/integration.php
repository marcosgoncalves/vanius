<?php
if(isset($_POST['integration'])){
  /*
  $data = $_POST['integration'];
  $user = $data['first_name'];
  $pass = $_POST['pass'];
  $email = $_POST['email'];
  $type = $data['types_id'];
  switch ($type) {
    case '1':
      $role = 'editor';
      break;
    case '2':
      $role = 'author';
      break;
    default:
      $role = 'subscriber';
      break;
  }
  if (!email_exists( $email )) {
          $user_id = wp_create_user( $user.rand(0,1000), $pass, $email );
          $user = new WP_User( $user_id );
          $user->set_role($role);
          echo 1;die;
  }
  echo 0;	die;
  */
 }

 function integration($user, $email){
?>
<script
  src="https://code.jquery.com/jquery-3.3.1.min.js"
  integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
  crossorigin="anonymous"></script>
<script type="text/javascript">

$(document).ready(function(){

  var api = 'https://app.forsocios.com/api/v1/users/login';
  var url = '<?php echo 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ?>';
  var method = 'POST';
	var email = '<?php echo $user ?>';
	var pass = '<?php echo $email ?>';

	if(email!==''){
    // $('body').hide();
		$.ajax({
			'url': api,
			'method': method,
			data: {email: email, password: pass},
			success: (r)=>{
				if(!r.e){
					$.ajax({
						'url': url,
						'method': 'POST',
						data: {integration: r.d,email:email, pass:pass},
						success: (ri)=>{if(ri=='1'){$('#user_login').val(email);$('#user_pass').val(pass);$('#wp-submit').click();}}
					});
				}else{
          $('body').show();
        }
			}
		})
	}
})

</script>
<?php } ?>
