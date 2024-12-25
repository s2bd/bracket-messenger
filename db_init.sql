-- Create the database
CREATE DATABASE bracket_messenger;

-- Use the database
USE bracket_messenger;

-- Create the User table
CREATE TABLE User (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    display_name VARCHAR(50),
    profile_image_url VARCHAR(255),
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_admin BOOLEAN DEFAULT FALSE
);

-- Create the Chat table
CREATE TABLE Chat (
    chat_id INT AUTO_INCREMENT PRIMARY KEY,
    creator_id INT NOT NULL,
    creation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES User(user_id)
);

-- Create the Chat_Members table to normalize chat membership and roles
CREATE TABLE Chat_Members (
    chat_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('member', 'moderator', 'creator') DEFAULT 'member',
    PRIMARY KEY (chat_id, user_id),
    FOREIGN KEY (chat_id) REFERENCES Chat(chat_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES User(user_id) ON DELETE CASCADE
);

-- Create the Message table
CREATE TABLE Message (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    sender_id INT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    body TEXT,
    image_url VARCHAR(255),
    link_url VARCHAR(255),
    FOREIGN KEY (chat_id) REFERENCES Chat(chat_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES User(user_id) ON DELETE CASCADE
);
