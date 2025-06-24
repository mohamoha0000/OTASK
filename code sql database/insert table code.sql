-- password:moha3456
INSERT INTO users (id, name, email, password, role, remember_token, profile_picture, created_at) VALUES
(1, 'Admin', 'moham3iof@gmail.com', '$2y$10$hvuOyrSyy01ucsBeiFvT4e6X9gNRgurcxanlIvI.QJEwuVUvG/jLe', 'admin', NULL, NULL, NOW()),
(2, 'Jane Doe', 'mohamedelmaeyouf@gmail.com', '$2y$10$hvuOyrSyy01ucsBeiFvT4e6X9gNRgurcxanlIvI.QJEwuVUvG/jLe', 'member', NULL, NULL, NOW()),
(3, 'John Smith', 'elmeayouf.mohamed.solicode@gmail.com', '$2y$10$hvuOyrSyy01ucsBeiFvT4e6X9gNRgurcxanlIvI.QJEwuVUvG/jLe', 'member', NULL, NULL, NOW());

INSERT INTO projects (id, title, description, visibility, supervisor_id, created_at) VALUES
(1, 'ecomerce project', 'Admin main project.', TRUE, 1, NOW()),
(2, 'full stak react', 'Jane personal project.', TRUE, 2, NOW()),
(3, 'pyhon flask', 'John personal project.', TRUE, 3, NOW());

INSERT INTO project_members (project_id, user_id) VALUES
(1, 1), (1, 2), (1, 3),
(2, 2), (2, 1), (2, 3),
(3, 3), (3, 1), (3, 2);

INSERT INTO tasks (project_id, title, description, start_date, end_date, last_mod, priority, status, assigned_user_id, created_by_id) VALUES
(1, 'make hedere and footer', 'Initial setup files', NOW(), NOW(), NOW(), 'medium', 'to_do', 1, 1),
(1, 'make Shopping cart js', 'Prepare project brief', NOW(), NOW(), NOW(), 'medium', 'to_do', 1, 1),
(1, 'main sections html css js', 'Design wireframes', NOW(), NOW(), NOW(), 'medium', 'to_do', 2, 1),
(1, 'style responsive', 'Style landing page', NOW(), NOW(), NOW(), 'medium', 'to_do', 2, 1),
(1, 'make sql for database', 'Database schema', NOW(), NOW(), NOW(), 'medium', 'to_do', 3, 1),
(1, 'login and sign up full stak', 'Auth system', NOW(), NOW(), NOW(), 'medium', 'to_do', 3, 1),

(2, 'Add carousel', 'Add carousel', NOW(), NOW(), NOW(), 'medium', 'to_do', 2, 2),
(2, 'Fix mobile navbar', 'Fix mobile navbar', NOW(), NOW(), NOW(), 'medium', 'to_do', 2, 2),
(2, 'Setup git repo', 'Setup git repo', NOW(), NOW(), NOW(), 'medium', 'to_do', 1, 2),
(2, 'Write README', 'Write README', NOW(), NOW(), NOW(), 'medium', 'to_do', 1, 2),
(2, 'Connect API', 'Connect API', NOW(), NOW(), NOW(), 'medium', 'to_do', 3, 2),
(2, 'Test endpoints', 'Test endpoints', NOW(), NOW(), NOW(), 'medium', 'to_do', 3, 2),

(3, 'Deploy to production', 'Deploy to production', NOW(), NOW(), NOW(), 'medium', 'to_do', 3, 3),
(3, 'Monitor logs', 'Monitor logs', NOW(), NOW(), NOW(), 'medium', 'to_do', 3, 3),
(3, 'UI final tweaks', 'UI final tweaks', NOW(), NOW(), NOW(), 'medium', 'to_do', 2, 3),
(3, 'Color palette update', 'Color palette update', NOW(), NOW(), NOW(), 'medium', 'to_do', 2, 3),
(3, 'Add cron jobs', 'Add cron jobs', NOW(), NOW(), NOW(), 'medium', 'to_do', 1, 3),
(3, 'Setup SSL', 'Setup SSL', NOW(), NOW(), NOW(), 'medium', 'to_do', 1, 3),

(NULL, 'Personal task 1', 'Admin task A', NOW(), NOW(), NOW(), 'low', 'to_do', 1, 1),
(NULL, 'Personal task 2', 'Admin task B', NOW(), NOW(), NOW(), 'low', 'to_do', 1, 1),
(NULL, 'Personal task 3', 'Admin task C', NOW(), NOW(), NOW(), 'low', 'to_do', 1, 1),
(NULL, 'Personal task 4', 'Admin task D', NOW(), NOW(), NOW(), 'low', 'to_do', 1, 1),
(NULL, 'Personal task 5', 'Admin task E', NOW(), NOW(), NOW(), 'low', 'to_do', 1, 1),

(NULL, 'Personal task 1', 'Jane task A', NOW(), NOW(), NOW(), 'low', 'to_do', 2, 2),
(NULL, 'Personal task 2', 'Jane task B', NOW(), NOW(), NOW(), 'low', 'to_do', 2, 2),
(NULL, 'Personal task 3', 'Jane task C', NOW(), NOW(), NOW(), 'low', 'to_do', 2, 2),
(NULL, 'Personal task 4', 'Jane task D', NOW(), NOW(), NOW(), 'low', 'to_do', 2, 2),
(NULL, 'Personal task 5', 'Jane task E', NOW(), NOW(), NOW(), 'low', 'to_do', 2, 2),

(NULL, 'John Personal task 1', 'John task A', NOW(), NOW(), NOW(), 'low', 'to_do', 3, 3),
(NULL, 'John Personal task 2', 'John task B', NOW(), NOW(), NOW(), 'low', 'to_do', 3, 3),
(NULL, 'John Personal task 3', 'John task C', NOW(), NOW(), NOW(), 'low', 'to_do', 3, 3),
(NULL, 'John Personal task 4', 'John task D', NOW(), NOW(), NOW(), 'low', 'to_do', 3, 3),
(NULL, 'John Personal task 5', 'John task E', NOW(), NOW(), NOW(), 'low', 'to_do', 3, 3);

INSERT INTO chat_messages (project_id, sender_id, message, sent_at) VALUES
(1, 1, 'Welcome to the Admin project!', NOW()),
(1, 2, 'I will start on the wireframes.', NOW()),
(1, 3, 'Database schema is done.', NOW()),
(2, 2, 'Working on carousel.', NOW()),
(2, 3, 'API connection ready.', NOW()),
(3, 3, 'Logs are clean.', NOW()),
(3, 2, 'Final UI updates done.', NOW());