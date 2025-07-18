-- 🧑 Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('member', 'admin') NOT NULL DEFAULT 'member',
    remember_token VARCHAR(255) DEFAULT NULL,
    profile_picture TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 📁 Projects Table
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    visibility BOOLEAN DEFAULT FALSE,
    supervisor_id INT NOT NULL, -- Assuming one mandatory supervisor per project
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE RESTRICT -- Corrected: Changed from CASCADE to RESTRICT for safer data integrity
);

-- 👥 Project Members Table
CREATE TABLE project_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    UNIQUE(project_id, user_id), -- Ensures a user can only be a member of a project once
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ✅ Tasks Table
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,  -- يمكن أن تكون NULL للمهام الشخصية (Can be NULL for personal tasks)
    title VARCHAR(255) NOT NULL,
    deliverable_link TEXT, -- Link for task deliverables/attachments (e.g., Google Drive link)
    description TEXT,
    start_date DATETIME,
    end_date DATETIME,
    last_mod DATETIME,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('in_progress','to_do','pending_review','revision_needed','completed') DEFAULT 'to_do',
    assigned_user_id INT, -- يمكن أن تكون NULL (Can be NULL if not yet assigned or a personal task without specific assignment)
    created_by_id INT NOT NULL, -- The user who created this task
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL, -- If project is deleted, personal tasks remain
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL, -- If assigned user is deleted, task remains unassigned
    FOREIGN KEY (created_by_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 💬 Chat Messages Table
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    sender_id INT NOT NULL, -- The user who sent the message
    message TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 🔔 Notifications Table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- The recipient of the notification
    sender_id INT,        -- NEW: The user who originated/sent the notification (NULL for system-generated notifications like deadlines)
    type ENUM('invite_to_project', 'invite_accepted', 'invite_declined', 'task_update', 'task_deadline', 'admin_message') NOT NULL,
    title VARCHAR(255) NOT NULL, -- Added title column
    message TEXT NOT NULL, -- Renamed content to message for clarity
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    related_id INT,        -- NEW: Stores ID of related entity (e.g., project_id for invites)
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL, -- If sender user is deleted, set this to NULL
    FOREIGN KEY (related_id) REFERENCES projects(id) ON DELETE SET NULL -- If related project is deleted, set this to NULL
);
