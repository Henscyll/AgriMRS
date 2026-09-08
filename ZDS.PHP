<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_type = $_POST['user_type']; // either 'farmer' or 'association'
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $region = $_POST['region'];
    $province = $_POST['province'];
    $municipality = $_POST['municipality'];
    $barangay = $_POST['barangay'];
    $address = "$region, $province, $municipality, $barangay";

    $conn = new mysqli("localhost", "root", "", "agri_machinery");
    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "Email already registered.";
    } else {
        if ($user_type === "farmer") {
            $stmt = $conn->prepare("INSERT INTO farmers (name, email, password, address, region, province, municipality, barangay) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $name, $email, $password, $address, $region, $province, $municipality, $barangay);
            $stmt->execute();

            $user_stmt = $conn->prepare("INSERT INTO users (name, email, password, user_role) VALUES (?, ?, ?, 'farmer')");
            $user_stmt->bind_param("sss", $name, $email, $password);
            $user_stmt->execute();
        } else if ($user_type === "association") {
            $stmt = $conn->prepare("INSERT INTO associations (name, email, password, address, region, province, municipality, barangay) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $name, $email, $password, $address, $region, $province, $municipality, $barangay);
            $stmt->execute();

            $user_stmt = $conn->prepare("INSERT INTO users (name, email, password, user_role) VALUES (?, ?, ?, 'association president')");
            $user_stmt->bind_param("sss", $name, $email, $password);
            $user_stmt->execute();
        }

        header("Location: login.php");
        exit;
    }

    $check->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sign Up — AgriMach</title>
  <style>
    body { 
      font-family: Arial, sans-serif; 
      background: #ecf1eb; 
    }
    .signup-box { 
      max-width: 500px; 
      margin: 50px auto; 
      padding: 30px; background: white; 
      border-radius: 10px; 
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); 
    }
    
    h2 { 
      text-align: center; 
      color: #4CAF50; 
      margin-bottom: 20px; 
    }
    label { 
      font-weight: bold; 
      display: block; 
      margin-top: 10px; 
    }
    input, select { 
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      margin-bottom: 15px;
      border-radius: 5px;
      border: 1px solid #ccc; 
    }

    button { 
      width: 100%; 
      padding: 12px; 
      background-color: #4CAF50; 
      border: none; color: white; 
      border-radius: 5px; font-size: 16px; 
    }

    button:hover { 
      background-color: #45a049; 
    }

    .error { 
      color: red; 
      text-align: center; 
    }

    .login-link { 
      text-align: center; 
      margin-top: 15px; 
    }

    .login-link a { 
      color: #4CAF50; 
      text-decoration: none; 
    }

    .login-link a:hover { 
      text-decoration: underline; 
    }

  </style>
</head>

<body>
  <div class="signup-box">
    <h2>Create Account</h2>

    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

    <form method="post">
      <label>User Type</label>
      <select name="user_type" required>
        <option value="">-- Select Type --</option>
        <option value="farmer">Farmer</option>
        <option value="association">Association </option>
      </select>

      <label>Full Name / Association Name</label><input name="name" required>
      <label>Email</label><input type="email" name="email" required>
      <label>Password</label><input type="password" name="password" required>
      <label>Region</label>

      <select id="region" name="region" required>
        <option value="">--Select Region--</option>
        <option value="Zamboanga Peninsula">Region IX</option>

      </select>
      <label>Province</label>
      <select id="province" name="province" required>
        <option value="">--Select Province--</option>

      </select>
      <label>Municipality</label>
      <select id="municipality" name="municipality" required>
        <option value="">--Select Municipality--</option>

      </select>
      <label>Barangay</label>
      <select id="barangay" name="barangay" required>
        <option value="">--Select Barangay--</option>

      </select>
      <button type="submit">Sign Up</button>
    </form>
    <div class="login-link">
      Already registered? <a href="login.php">Login here</a>
    </div>
  </div>

  <script>
    const data = {
      "Zamboanga Peninsula": {
        "Zamboanga del Sur": {

          "Pagadian City": ["Alegria", "Balangasan", "Balintawak", "Baloyboan", "Banale", "Bogo", "Bomba", "Buenavista", "Bulatok",
          "Bulawan","Dampalan","Danlugan","Dao","Datagan","Deborok","Ditoray","Dumagoc","Gatas","Gubac","Gubang","Kagawasan","Kahayagan",
          "Kalasan","Kawit","La Suerte","Lala","Lapidian","Lenienza","Lizon Valley","Lourdes","Lower Sibatang","Lumad","Lumbia","Macasing",
          "Manga","Muricay","Napolan","Palpalan","Pedulonan","Poloyagan","San Francisco","San Jose","San Pedro","Santa Lucia","Santa Maria",
          "Santiago","Santo Niño","Tawagan Sur","Tiguma","Tuburan","Tulangan","Tulawas","Upper Sibatang","White Beach"],
          
          "Aurora": [
            "Acad", "Alang-alang", "Alegria", "Anonang", "Bagong Mandaue", "Bagong Maslog", "Bagong Oslob", "Bagong Pitogo",
            "Baki", "Balas", "Balide", "Balintawak", "Bayabas", "Bemposa", "Cabilinan", "Campo Uno", "Ceboneg",
            "Commonwealth", "Gubaan", "Inasagan", "Inroad", "Kahayagan East (Katipunan)", "Kahayagan West", "Kauswagan",
            "La Paz (Tinibtiban)", "La Victoria", "Lantungan", "Libertad", "Lintugop", "Lubid", "Maguikay", "Mahayahay",
            "Monte Alegre", "Montela", "Napo", "Panaghiusa", "Poblacion", "Resthouse", "Romarate", "San Jose", "San Juan",
            "Sapa Loboc", "Tagulalo", "Waterfall"],

          "Bayog": [
            "Baking", "Balukbahan", "Balumbunan", "Bantal", "Bobuan", "Camp Blessing", "Canoayan", "Conacon", "Dagum",
            "Damit", "Datagan", "Depase", "Dipili", "Depore", "Deporehan", "Dimalinao", "Kahayagan", "Kanipaan", "Lamare",
            "Liba", "Mataga", "Matin-ao", "Matun-og", "Pangi (San Isidro)", "Poblacion", "Pulang Bato", "Salawagan",
            "San Isidro", "Sigacad", "Supon"],
          
          "Dimataling": [
            "Bacayawan", "Baha", "Balanagan", "Baluno", "Binuay", "Buburay", "Grap", "Josefina", "Kagawasan", "Lalab",
            "Libertad", "Magahis", "Mahayag", "Mercedes", "Poblacion", "Saloagan", "San Roque", "Sugbay Uno", "Sumbato",
            "Sumpot", "Tinggabulong", "Tiniguangan", "Tipangi", "Upper Ludiong"],


          "Dinas": [
            "Bacawan", "Benuatan", "Beray", "Don Jose", "Dongos", "East Migpulao", "Guinicolalay",
            "Ignacio Garrata (New Mirapao)", "Kinakap", "Legarda 1", "Legarda 2", "Legarda 3", "Lower Dimaya", "Locuban",
            "Ludiong", "Nangka", "Nian", "Old Mirapao", "Pisa-an", "Poblacion", "Proper Dimaya", "Sagacad", "Sambulawan",
            "San Isidro", "Songayan", "Sumpotan", "Tarakan", "Upper Dimaya", "Upper Sibul", "West Migpulao"],
          
          "Dumalinao": [
            "Anonang", "Bag-ong Misamis", "Baga", "Bag-ong Silao", "Baloboan", "Banta-ao", "Bibilik", "Calingayan",
            "Camalig", "Camanga", "Cuatro-cuatro", "Locuban", "Malasik", "Mama (San Juan)", "Matab-ang", "Mecolong",
            "Metokong", "Motosawa", "Pag-asa (Poblacion)", "Paglaum (Poblacion)", "Pantad", "Piniglibano", "Rebokon",
            "San Agustin", "Sibucao", "Sumadat", "Tikwas", "Tina", "Tubo-Pait", "Upper Dumalinao"],


          "Dumingag": [
            "Bag-ong Valencia","Bagong Kauswagan","Bagong Silang","Bucayan","Calumanggi","Canibongan",
            "Caridad","Danlugan","Dapiwak","Datu Totocan","Dilud","Ditulan","Dulian","Dulop",
            "Guintananan","Guitran","Gumpingan","La Fortuna","Labangon","Libertad","Licabang","Lipawan",
            "Lower Landing","Lower Timonan","Macasing","Mahayahay","Malagalad","Manlabay","Maralag",
            "Marangan","New Basak","Saad","Salvador","San Juan","San Pablo (Poblacion)",
            "San Pedro (Poblacion)","San Vicente","Senote","Sinonok","Sunop","Tagun","Tamurayan",
            "Upper Landing","Upper Timonan"],

          "Guipos": [
            "Bagong Oroquieta","Baguitan","Balongating","Canunan","Dacsol","Dagohoy","Dalapang",
            "Datagan","Guling","Katipunan","Lintum","Litan","Magting","Poblacion","Regla",
            "Sikatuna","Singclot"],

          "Josefina": [
            "Bogo Calabat","Dawa (Diwa)","Ebarle","Gumahan (Poblacion)","Leonardo","Litapan",
            "Lower Bagong Tudela","Mansanas","Moradji","Nemeño","Nopulan","Sebukang",
            "Tagaytay Hill","Upper Bagong Tudela (Poblacion)"],

          "Kumalarang": [
            "Bogayo","Bolisong","Boyugan East","Boyugan West","Bualan","Diplo","Gawil","Gusom",
            "Kitaan Dagat","Lantawan","Limamawan","Mahayahay","Pangi","Picanan","Poblacion",
            "Salagmanok","Secade","Suminalum"],

          "Labangan": [
            "Bagalupa","Balimbingan ","Binayan","Bokong","Bulanit","Cogonan","Combo",
            "Dalapang","Dimasangca","Dipaya","Langapod","Lantian","Lower Campo Islam ",
            "Lower Pulacan","Lower Sang-an","New Labangan","Noboran","Old Labangan","San Isidro",
            "Santa Cruz","Tapodoc","Tawagan Norte","Upper Campo Islam ",
            "Upper Pulacan","Upper Sang-an"],

          "Lakewood": [
            "Bagong Kahayag","Baking","Biswangan","Bululawan","Dagum","Gasa","Gatub","Lukuan",
            "Matalang","Poblacion","Sapang Pinoles","Sebuguey","Tiwales","Tubod"],

          "Lapuyan": [
            "Alihis","Balabawan","Bulawan","Culayan","Danan","Lakewood","Lubusan","Malinis",
            "Maruing","Pampang","Pingalay","Poblacion","Sayog","Tiguha"],
            
          "Mahayag": [
            "Bag-ong Balamban","Bag-ong Dalaguete","Bag-ong Labangon","Bag-ong Salong","Boniao",
            "Diano","Kauswagan","Lower Salug Daku","Lower Santo Niño","Manguiles","Molocoloc",
            "Paraiso","Poblacion (Mahayag North)","Poblacion (Mahayag South)","Salug Daku",
            "San Isidro","San Jose","San Vicente","Santo Niño","Tiguha","Tulog"],
            
          "Midsalip": [
            "Balonai","Benigno Aquino","Bulawan","Cumarom","Dadiangao","Datalna","Datagan",
            "Dulop","Ecuan","Guitalos","Ilayang Labangon","Lower Sibatang","Lumbog","Lumbog Proper",
            "Lupok","Macasing","Malagalad","Maragang","Pawan","Piwan","Poblacion A",
            "Poblacion B","Salvador","San Jose","San Juan","San Pedro","San Vicente","Tala-o",
            "Upper Sibatang"],

          "Molave": [
            "Bag-ong Argao","Bag-ong Gutlang","Bag-ong Nayon","Blancia","Caniangan","Cogon",
            "Dipolo","Gonosan","Lower Dimorok","Lower Madasigon","Maloloy-on","Miligan","Pulot",
            "Purok","Rizal","Upper Dimorok","Upper Madasigon","Madasigon","Poblacion"],

          "Pitogo": [
            "Balongbalong","Balongating","Balubuan","Batalon","Culubi","Danganan","Guitan",
            "Katipunan","Liguac","Limbayan","Lukatan","Lumbayao","Mawal","Panubigan",
            "Poblacion","Sugbay Dos","Talanusa","Tipan","Tuboran"],

          "Ramon Magsaysay": [
            "Babuyan","Campo V","Esperanza","Gapasan","Katipunan","Langon","Lumpanac",
            "Lumbog","Mabini","Magsaysay","Poblacion","Rizal","San Isidro","San Jose",
            "San Vicente","Santo Niño","Sindangan","Tiogan","Trinidad"],

          "San Miguel": [
            "Bacahan","Bonglao","Calube","Danganan","Dinawehan","Ladlad","Laperian","Libertad",
            "Linay","Loboc","Mabuhay","Maligaya","Ocapan","Poblacion","Rizal","Sabang","Sagucan",
            "San Isidro","Santo Niño","Sumpotan","Tiguha"],

          "San Pablo": [
            "Baclay","Bag-ong Misamis","Bubual","Buton","Culasian","Daan Lungsod","Kalilangan",
            "Kapamanok","Kapatagan","Lumbayao","Lumbog","Malamoy","Maragang","Poblacion",
            "San Juan","Talanusa","Tumbagan","Tumpagon","Tungawan"],

          "Sominot": [
            "Bag-ong Baroy","Barubuhan","Bayog","Culabay","Datagan","Dumalian","Libertad",
            "Malaubang","Mandingan","Maragang","Poblacion","San Miguel","San Vicente","Silangit",
            "Sumalig"],

          "Tabina": [
            "Abong-abong","Bacalan","Baya-baya","Baya-baya Proper","Capisan","Concepcion",
            "Dungguan","Kinakap","Lambulayag","Landi","Lomonay","Manicaan","New Oroquita",
            "Poblacion","Sanhagon","Tultolan","Tupilac"],

          "Tambulig": [
            "Abaga","Bagong Kapatagan","Bagong Tabang","Balukbahan","Dipolo","Kahayagan",
            "Lison Valley","Lourdes","Lower Lodiong","Magsaysay","Mahayahay","Maragang",
            "Molocboloc","Poblacion","Rizal","San Antonio","San Jose","Santo Niño",
            "Sulong Daku","Sulong Small","Upper Lodiong"],

          "Tigbao": [
            "Batayan","Biasong","Calalasan","Culasian","Datagan","Digan","Guinlin","Kahayagan",
            "Limas","Lumbayao","Maragang","Matalang","Poblacion","Sagangon","San Agustin",
            "San Isidro","Santo Niño","Timolan","Tubod"],

          "Tukuran": [
            "Alindahaw","Baclay","Balangasan","Bayao","Bel-is","Betinan","Camanga","Dulop",
            "Kalumangan","Lower Bayao","Mabuhay","Navalan","Poblacion","Rizal","San Carlos",
            "San Isidro","Tagulo","Upper Bayao"],

          "Vincenzo A. Sagun": [
            "Ambulon","Biu-os","Cogon","Kapatagan","Laperian","Linoguayan","Lumbal",
            "Maraya","Navalan","Poblacion","Sagucan","San Agustin","San Antonio",
            "San Juan","Waling-waling"],

        }
      }
    };

    const region = document.getElementById("region");
    const prov = document.getElementById("province");
    const muni = document.getElementById("municipality");
    const brgy = document.getElementById("barangay");

    region.addEventListener("change", () => {
      prov.innerHTML = "<option value=''>--Select Province--</option>";
      muni.innerHTML = "<option value=''>--Select Municipality--</option>";
      brgy.innerHTML = "<option value=''>--Select Barangay--</option>";
      const provs = data[region.value] || {};
      Object.keys(provs).forEach(p => {
        prov.innerHTML += `<option value="${p}">${p}</option>`;
      });
    });

    prov.addEventListener("change", () => {
      muni.innerHTML = "<option value=''>--Select Municipality--</option>";
      brgy.innerHTML = "<option value=''>--Select Barangay--</option>";
      const munis = data[region.value]?.[prov.value] || {};
      Object.keys(munis).forEach(m => {
        muni.innerHTML += `<option value="${m}">${m}</option>`;
      });
    });

    muni.addEventListener("change", () => {
      brgy.innerHTML = "<option value=''>--Select Barangay--</option>";
      const barangays = data[region.value]?.[prov.value]?.[muni.value] || [];
      barangays.forEach(b => {
        brgy.innerHTML += `<option value="${b}">${b}</option>`;
      });
    });
  </script>
</body>
</html>
