-- password:moha3456
INSERT INTO users (id, name, email, password, role, remember_token, profile_picture, created_at) VALUES
(1, 'Admin', 'moham3iof@gmail.com', '$2y$10$hvuOyrSyy01ucsBeiFvT4e6X9gNRgurcxanlIvI.QJEwuVUvG/jLe', 'admin', NULL, NULL, NOW()),
(2, 'Jane Doe', 'mohamedelmaeyouf@gmail.com', '$2y$10$hvuOyrSyy01ucsBeiFvT4e6X9gNRgurcxanlIvI.QJEwuVUvG/jLe', 'member', NULL, NULL, NOW()),
(3, 'John Smith', 'elmeayouf.mohamed.solicode@gmail.com', '$2y$10$hvuOyrSyy01ucsBeiFvT4e6X9gNRgurcxanlIvI.QJEwuVUvG/jLe', 'member', NULL, NULL, NOW());

INSERT INTO projects (id, title, description, visibility, supervisor_id, created_at) VALUES
(1, 'Admin Project', 'Admin main project.', TRUE, 1, NOW()),
(2, 'Jane Project', 'Jane personal project.', TRUE, 2, NOW()),
(3, 'John Project', 'John personal project.', TRUE, 3, NOW());

INSERT INTO project_members (project_id, user_id) VALUES
(1, 1), (1, 2), (1, 3),
(2, 2), (2, 1), (2, 3),
(3, 3), (3, 1), (3, 2);

INSERT INTO tasks (project_id, title, description, start_date, end_date, last_mod, priority, status, assigned_user_id, created_by_id) VALUES
(1, 'Admin T1', 'Initial setup files', NOW(), NOW(), NOW(), 'medium', 'to_do', 1, 1),
(1, 'Admin T2', 'Prepare project brief', NOW(), NOW(), NOW(), 'medium', 'to_do', 1, 1),
(1, 'Jane T1', 'Design wireframes', NOW(), NOW(), NOW(), 'medium', 'to_do', 2, 1),
(1, 'Jane T2', 'Style landing page', NOW(), NOW(), NOW(), 'medium', 'to_do', 2, 1),
(1, 'John T1', 'Database schema', NOW(), NOW(), NOW(), 'medium', 'to_do', 3, 1),
(1, 'John T2', 'Auth system', NOW(), NOW(), NOW(), 'medium', 'to_do', 3, 1),

(2, 'Jane T3', 'Add carousel', NOW(), NOW(), NOW(), 'medium', 'to_do', 2, 2),
(2, 'Jane T4', 'Fix mobile navbar', NOW(), NOW(), NOW(), 'medium', 'to_do', 2, 2),
(2, 'Admin T3', 'Setup git repo', NOW(), NOW(), NOW(), 'medium', 'to_do', 1, 2),
(2, 'Admin T4', 'Write README', NOW(), NOW(), NOW(), 'medium', 'to_do', 1, 2),
(2, 'John T3', 'Connect API', NOW(), NOW(), NOW(), 'medium', 'to_do', 3, 2),
(2, 'John T4', 'Test endpoints', NOW(), NOW(), NOW(), 'medium', 'to_do', 3, 2),

(3, 'John T5', 'Deploy to production', NOW(), NOW(), NOW(), 'medium', 'to_do', 3, 3),
(3, 'John T6', 'Monitor logs', NOW(), NOW(), NOW(), 'medium', 'to_do', 3, 3),
(3, 'Jane T5', 'UI final tweaks', NOW(), NOW(), NOW(), 'medium', 'to_do', 2, 3),
(3, 'Jane T6', 'Color palette update', NOW(), NOW(), NOW(), 'medium', 'to_do', 2, 3),
(3, 'Admin T5', 'Add cron jobs', NOW(), NOW(), NOW(), 'medium', 'to_do', 1, 3),
(3, 'Admin T6', 'Setup SSL', NOW(), NOW(), NOW(), 'medium', 'to_do', 1, 3),

(NULL, 'Admin Personal 1', 'Admin task A', NOW(), NOW(), NOW(), 'low', 'to_do', 1, 1),
(NULL, 'Admin Personal 2', 'Admin task B', NOW(), NOW(), NOW(), 'low', 'to_do', 1, 1),
(NULL, 'Admin Personal 3', 'Admin task C', NOW(), NOW(), NOW(), 'low', 'to_do', 1, 1),
(NULL, 'Admin Personal 4', 'Admin task D', NOW(), NOW(), NOW(), 'low', 'to_do', 1, 1),
(NULL, 'Admin Personal 5', 'Admin task E', NOW(), NOW(), NOW(), 'low', 'to_do', 1, 1),

(NULL, 'Jane Personal 1', 'Jane task A', NOW(), NOW(), NOW(), 'low', 'to_do', 2, 2),
(NULL, 'Jane Personal 2', 'Jane task B', NOW(), NOW(), NOW(), 'low', 'to_do', 2, 2),
(NULL, 'Jane Personal 3', 'Jane task C', NOW(), NOW(), NOW(), 'low', 'to_do', 2, 2),
(NULL, 'Jane Personal 4', 'Jane task D', NOW(), NOW(), NOW(), 'low', 'to_do', 2, 2),
(NULL, 'Jane Personal 5', 'Jane task E', NOW(), NOW(), NOW(), 'low', 'to_do', 2, 2),

(NULL, 'John Personal 1', 'John task A', NOW(), NOW(), NOW(), 'low', 'to_do', 3, 3),
(NULL, 'John Personal 2', 'John task B', NOW(), NOW(), NOW(), 'low', 'to_do', 3, 3),
(NULL, 'John Personal 3', 'John task C', NOW(), NOW(), NOW(), 'low', 'to_do', 3, 3),
(NULL, 'John Personal 4', 'John task D', NOW(), NOW(), NOW(), 'low', 'to_do', 3, 3),
(NULL, 'John Personal 5', 'John task E', NOW(), NOW(), NOW(), 'low', 'to_do', 3, 3);

INSERT INTO chat_messages (project_id, sender_id, message, sent_at) VALUES
(1, 1, 'Welcome to the Admin project!', NOW()),
(1, 2, 'I will start on the wireframes.', NOW()),
(1, 3, 'Database schema is done.', NOW()),
(2, 2, 'Working on carousel.', NOW()),
(2, 3, 'API connection ready.', NOW()),
(3, 3, 'Logs are clean.', NOW()),
(3, 2, 'Final UI updates done.', NOW());

INSERT INTO notifications (user_id, sender_id, type, content, is_read, created_at) VALUES
(1, 2, 'admin_message', 'Jane sent a message on Admin Project.', 0, NOW()),
(2, 1, 'admin_message', 'You have been assigned to Project 1.', 0, NOW()),
(3, 1, 'admin_message', 'You have been assigned a new task.', 0, NOW()),
(2, 3, 'admin_message', 'John replied to your message.', 1, NOW()),
(3, 2, 'admin_message', 'Jane mentioned you in a chat.', 0, NOW());
