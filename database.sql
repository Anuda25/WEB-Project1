

CREATE DATABASE IF NOT EXISTS court_case_system;
USE court_case_system;


CREATE TABLE IF NOT EXISTS users (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,
    Email VARCHAR(100) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    Role ENUM('Admin', 'Clerk', 'Judge', 'Lawyer') NOT NULL,
    Phone VARCHAR(20),
    Status ENUM('Active', 'Inactive') DEFAULT 'Active',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


INSERT INTO users (Name, Email, Password, Role, Phone, Status)
VALUES ('System Admin', 'admin@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', '1234567890', 'Active')
ON DUPLICATE KEY UPDATE Email='admin@system.com';


CREATE TABLE IF NOT EXISTS cases (
    CaseID INT AUTO_INCREMENT PRIMARY KEY,
    CaseNumber VARCHAR(50) NOT NULL UNIQUE,
    CaseTitle VARCHAR(255) NOT NULL,
    CaseType VARCHAR(100) NOT NULL,
    FilingDate DATE NOT NULL,
    Description TEXT,
    Status ENUM('Open', 'In Progress', 'Closed', 'Appealed') DEFAULT 'Open',
    CreatedBy INT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CreatedBy) REFERENCES users(UserID) ON DELETE SET NULL
);


CREATE TABLE IF NOT EXISTS courtrooms (
    CourtroomID INT AUTO_INCREMENT PRIMARY KEY,
    RoomNumber VARCHAR(50) NOT NULL UNIQUE,
    Location VARCHAR(255) NOT NULL,
    Capacity INT
);


INSERT IGNORE INTO courtrooms (RoomNumber, Location, Capacity) VALUES 
('Room 101', 'Floor 1, West Wing', 50),
('Room 102', 'Floor 1, East Wing', 30),
('Room 201', 'Floor 2, Main Hall', 100);


CREATE TABLE IF NOT EXISTS hearings (
    HearingID INT AUTO_INCREMENT PRIMARY KEY,
    CaseID INT NOT NULL,
    HearingDate DATE NOT NULL,
    HearingTime TIME NOT NULL,
    CourtroomID INT,
    JudgeID INT,
    Status ENUM('Scheduled', 'Completed', 'Cancelled', 'Rescheduled') DEFAULT 'Scheduled',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CaseID) REFERENCES cases(CaseID) ON DELETE CASCADE,
    FOREIGN KEY (CourtroomID) REFERENCES courtrooms(CourtroomID) ON DELETE SET NULL,
    FOREIGN KEY (JudgeID) REFERENCES users(UserID) ON DELETE SET NULL
);


CREATE TABLE IF NOT EXISTS documents (
    DocumentID INT AUTO_INCREMENT PRIMARY KEY,
    CaseID INT NOT NULL,
    FileName VARCHAR(255) NOT NULL,
    FilePath VARCHAR(255) NOT NULL,
    FileType VARCHAR(50),
    UploadDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UploadedBy INT,
    DocumentStatus ENUM('Pending', 'Verified', 'Rejected') DEFAULT 'Pending',
    FOREIGN KEY (CaseID) REFERENCES cases(CaseID) ON DELETE CASCADE,
    FOREIGN KEY (UploadedBy) REFERENCES users(UserID) ON DELETE SET NULL
);


CREATE TABLE IF NOT EXISTS notifications (
    NotificationID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    Message TEXT NOT NULL,
    Date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('Unread', 'Read') DEFAULT 'Unread',
    FOREIGN KEY (UserID) REFERENCES users(UserID) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS case_assignments (
    AssignmentID INT AUTO_INCREMENT PRIMARY KEY,
    CaseID INT NOT NULL,
    UserID INT NOT NULL, -- Can be Lawyer or Judge
    AssignedDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CaseID) REFERENCES cases(CaseID) ON DELETE CASCADE,
    FOREIGN KEY (UserID) REFERENCES users(UserID) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (CaseID, UserID)
);
