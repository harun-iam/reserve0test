<?php
require_once 'functions.php';

session_start();

//DBに接続
$pdo = new PDO('mysql:dbname=' . DB_NAME . ';host=' . DB_HOST . ';', DB_USER, DB_PASSWORD);
$pdo->query('SET NAMES utf8;');

//shopデータを取得
$stmt = $pdo->prepare('SELECT * FROM shop WHERE id = :id');
$stmt->bindValue(':id', 1, PDO::PARAM_INT);
$stmt->execute();
$shop = $stmt->fetch();

//予約確定ボタンが推された場合の処理
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  //セッションから入力情報を取得する
  if (isset($_SESSION['RESERVE'])) {
    $reserve_date = $_SESSION['RESERVE']['reserve_date'];
    $reserve_num = $_SESSION['RESERVE']['reserve_num'];
    $reserve_time = $_SESSION['RESERVE']['reserve_time'];
    $name = $_SESSION['RESERVE']['name'];
    $email = $_SESSION['RESERVE']['email'];
    $tel = $_SESSION['RESERVE']['tel'];
    $comment = $_SESSION['RESERVE']['comment'];

    //TODO:予約が確定可能かどうか最終チェック
    //DBのreserveテーブルからその日時の「予約成立済み人数」を取得
    $stmt = $pdo->prepare("SELECT SUM(reserve_num) FROM reserve 
      WHERE DATE_FORMAT(reserve_date, '%Y%m%d') = :reserve_date AND DATE_FORMAT(reserve_time, '%H:%i') = :reserve_time 
      GROUP BY reserve_date, reserve_time LIMIT 1");
    $stmt->bindValue(':reserve_date', $reserve_date, PDO::PARAM_STR);
    $stmt->bindValue(':reserve_time', $reserve_time, PDO::PARAM_STR);
    $stmt->execute();
    $reserve_count = $stmt->fetchColumn();

    // 1時間当たりの予約上限チェック
    if ($reserve_count && ($reserve_count + $reserve_num) > $shop['max_reserve_num']) {
      $err['common'] = 'この日時はすでに予約が埋まっております。<br>予約画面に戻って予約情報を変更してください。';
    }

    //エラーが無ければ次の処理に進む
    if (empty($err)) {
      //reserveテーブルにINSERT
      $stmt = $pdo->prepare('INSERT INTO reserve (reserve_date, reserve_time, reserve_num, name, email, tel, comment) VALUES (:reserve_date, :reserve_time, :reserve_num, :name, :email, :tel, :comment)');
      $stmt->bindValue(':reserve_date', $reserve_date, PDO::PARAM_STR);
      $stmt->bindValue(':reserve_time', $reserve_time, PDO::PARAM_STR);
      $stmt->bindValue(':reserve_num', $reserve_num, PDO::PARAM_INT);
      $stmt->bindValue(':name', $name, PDO::PARAM_STR);
      $stmt->bindValue(':email', $email, PDO::PARAM_STR);
      $stmt->bindValue(':tel', $tel, PDO::PARAM_STR);
      $stmt->bindValue(':comment', $comment, PDO::PARAM_STR);
      $stmt->execute();

      //予約者にメール送信
      $from = 'From Web予約システムReserve <' . ADMIN_EMAIL . '>';

      $view_reserve_date = format_date($reserve_date);

      $subject = 'ご予約が確定しました。';
      $body = <<<EOT
    {$name}様

    以下の内容でご予約を承りました。

    ご予約内容
    [日時]{$view_reserve_date} {$reserve_time}
    [人数]{reserve_num}人
    [氏名]{$name}
    [メールアドレス]{$email}
    [電話番号]{$tel}
    [備考]{$comment}

    ご来店お待ちしております。
    EOT;

      //TODO:メール送信テストはサーバー上で実施
      //mb_send_mail($email, $subject, $body, $from);

      //店舗管理者にメール送信
      $subject = '【Reserve】ご予約が確定しました。';
      $body = <<<EOT
     以下の内容でご予約が確定しました。
 
     ご予約内容
     [日時]{$view_reserve_date} {$reserve_time}
     [人数]{reserve_num}人
     [氏名]{$name}
     [メールアドレス]{$email}
     [電話番号]{$tel}
     [備考]{$comment}
     EOT;

      //TODO:メール送信テストはサーバー上で実施
      //mb_send_mail(ADMIN_EMAIL, $subject, $body, $from);

      //予約が正常に完了したらセッションのデータをクリアする
      unset($_SESSION['RESERVE']);

      //DBから切断
      unset($pdo);

      //予約完了画面の表示
      header('Location: complete.php');
      exit;
    }
  } else {
    //セッションからデータを取得できない場合はエラー
    //TODO：エラー処理
  }
}
?>

<!doctype html>
<html lang="ja">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

  <!-- Original CSS-->
  <link rel="stylesheet" href="css/style.css">
  <title>予約内容確認</title>
</head>

<body>
  <header>SAMPLE SHOP</header>
  <h1>予約内容確認</h1>

  <form method="post">

    <?php if (isset($err['common'])) : ?>
      <div class="alert alert-danger" role="alert"> <?= $err['common'] ?></div>
    <?php endif; ?>

    <table class="table">
      <tbody>
        <tr>
          <th scope="row">日時</th>
          <td> <?= format_date($_SESSION['RESERVE']['reserve_date']) ?> <?= $_SESSION['RESERVE']['reserve_time'] ?> </td>
        </tr>
        <tr>
          <th scope="row">人数</th>
          <td> <?= $_SESSION['RESERVE']['reserve_num'] ?>名</td>
        </tr>
        <tr>
          <th scope="row">氏名</th>
          <td colspan="2"> <?= $_SESSION['RESERVE']['name'] ?> </td>
        </tr>
        <tr>
          <th scope="row">メールアドレス</th>
          <td colspan="2"> <?= $_SESSION['RESERVE']['email'] ?> </td>
        </tr>
        <tr>
          <th scope="row">電話番号</th>
          <td colspan="2"> <?= $_SESSION['RESERVE']['tel'] ?> </td>
        </tr>
        <tr>
          <th scope="row">備考</th>
          <td colspan="2"> <?= nl2br($_SESSION['RESERVE']['comment']) ?> </td>
        </tr>
      </tbody>
    </table>

    <div class="d-grid gap-2 mx-3">
      <button class="btn btn-primary rounded-pill" type="submit">予約確定</button>
      <a class="btn btn-secondary rounded-pill" href="http://localhost/reserve0test/web/index.php">戻る</a>
    </div>
  </form>

  <!-- Optional JavaScript; choose one of the two! -->

  <!-- Option 1: Bootstrap Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

  <!-- Option 2: Separate Popper and Bootstrap JS -->
  <!--
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js" integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js" integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF" crossorigin="anonymous"></script>
    -->
</body>

</html>