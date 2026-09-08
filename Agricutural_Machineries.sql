--CREATE TABLE reservations (
   -- id INT AUTO_INCREMENT PRIMARY KEY,
    --farmer_id INT,
    --machine_type VARCHAR(50), -- e.g., 'Tractor' or 'Harvester'
    --machine_name VARCHAR(100),
    --reservation_date DATE,
    --status VARCHAR(50) DEFAULT 'Pending'
--);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_role ENUM('it admin', 'associations', 'operator', 'farmer') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE farmers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


--CREATE TABLE contact_messages (
 --   id INT AUTO_INCREMENT PRIMARY KEY,
  --  name VARCHAR(100),
   -- email VARCHAR(100),
   -- message TEXT,
   --- created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
--);

CREATE TABLE barangays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  region VARCHAR(100),
  province VARCHAR(100),
  municipality VARCHAR(100),
  barangay VARCHAR(255)
);



INSERT INTO users (name, email, password, user_role)
VALUES (
  'Admin',
  'admin@gmail.com',
  '$2y$10$f/sbhB8L9BbqlrRvF3DU4.TqsxXyuywe6nOoPKHwV9/J2Lgv1eeZu',
  'it admin'
);

CREATE TABLE about_us (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE farmers 
ADD region VARCHAR(100),
ADD province VARCHAR(100),
ADD municipality VARCHAR(100),
ADD barangay VARCHAR(100);

INSERT INTO about_us (content) VALUES (
    '<p>The <strong>Agricultural Machineries Reservation and Monitoring System</strong> is designed to assist Filipino farmers in accessing modern machinery such as tractors and harvesters through a convenient reservation platform.</p>
    <p>Our goal is to support agricultural communities by providing a system that simplifies machinery rental, encourages efficiency in farm operations, and improves transparency in cooperative services.</p>
    <p>This system is built in collaboration with local agricultural cooperatives, ensuring that services are aligned with the actual needs of our farmers.</p>
    <p><strong>What We Offer:</strong></p>
    <ul>
        <li>Easy booking of tractors and harvesters</li>
        <li>Real-time reservation monitoring</li>
        <li>Farmer-friendly user interface</li>
        <li>Support from cooperative management and IT admins</li>
    </ul>
    <p>Together, let\'s grow a better and more productive future in Philippine agriculture.</p>'
);


CREATE TABLE associations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    region VARCHAR(100),
    province VARCHAR(100),
    municipality VARCHAR(100),
    barangay VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL
);


ALTER TABLE farmers ADD phone VARCHAR(15);
ALTER TABLE associations ADD phone VARCHAR(15);




-- -This is for agricultural machines where you can add edit'

CREATE TABLE agricultural_machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    association_id INT,
    machine_name VARCHAR(100),
    machine_type VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE agricultural_machines ADD image_path VARCHAR(255);





CREATE TABLE contact_info (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    facebook_link VARCHAR(255) NOT NULL,
    address TEXT NOT NULL
);

INSERT INTO contact_info (email, phone, facebook_link, address)
VALUES ('agrimach@gmail.com', '0928-220-6170', 'https://facebook.com/agrimach', 'Brgy. Linienza, Pagadian City, Philippines');

ALTER TABLE farmers ADD COLUMN lot_number VARCHAR(50) AFTER barangay;


ALTER TABLE users MODIFY user_role 
ENUM('it admin', 'associations', 'operator', 'farmer', 'department of agriculture', 'da_staff') 
NOT NULL;


INSERT INTO users (name, email, password, user_role)
VALUES (
  'DA Official',
  'da@gmail.com',
  '$2y$10$jbWn4mEEIkI38GVL2gnMQO.b6Sp4e4E09QV8i8iTrOAxNq3.5JIea', -- hash of "da123"
  'department of agriculture'
);



ALTER TABLE associations ADD COLUMN user_id INT, ADD FOREIGN KEY (user_id) REFERENCES users(id);

ALTER TABLE farmers 
MODIFY password VARCHAR(255) NOT NULL;


CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    farmer_id INT NOT NULL,
    booking_date DATE NOT NULL,
    status ENUM('Pending', 'Approved', 'Completed', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_name VARCHAR(100) NOT NULL,
    type ENUM('Tractor', 'Harvester') NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    description TEXT,
    association_id INT,
    status ENUM('Active', 'Inactive', 'Under Maintenance') DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    farmer_id INT NOT NULL,
    booking_date DATE NOT NULL,
    farm_location VARCHAR(255) NOT NULL,
    farm_size DECIMAL(10,2) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('Pending', 'Approved', 'Completed', 'Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_farmer_id (farmer_id),
    INDEX idx_machine_id (machine_id),
    INDEX idx_booking_date (booking_date),
    INDEX idx_status (status)
);

-- If table already exists, add missing columns (run these one by one if needed)
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS farm_location VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS farm_size DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
