-- ============================================================
-- CASTRO'S READY CMS - COMPLETE FRESH INSTALL DATABASE
-- Current schema includes: core CMS, roles/security, media,
-- service badges, videos, Mission/Vision artwork gallery and
-- the approved initial YouTube videos.
-- Use ONLY for a new/fresh installation.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  full_name VARCHAR(150) NULL,
  email VARCHAR(180) NULL,
  avatar_path VARCHAR(500) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(64) NULL,
  last_user_agent VARCHAR(500) NULL,
  two_factor_secret_enc TEXT NULL,
  two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_admin_username (username), KEY idx_admin_email (email), KEY idx_admin_role_active(role_id,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_reset_token (token_hash),
  KEY idx_admin_reset_user (admin_id),
  KEY idx_admin_reset_expiry (expires_at),
  CONSTRAINT fk_admin_reset_user FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_remember_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  selector CHAR(18) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_remember_selector (selector),
  KEY idx_admin_remember_user (admin_id),
  KEY idx_admin_remember_expiry (expires_at),
  CONSTRAINT fk_admin_remember_user FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_content (
  content_key VARCHAR(100) NOT NULL,
  content_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (content_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  details TEXT NOT NULL,
  icon_path VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS videos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  video_type ENUM('youtube','vimeo','upload') NOT NULL DEFAULT 'youtube',
  video_url VARCHAR(700) NULL,
  file_path VARCHAR(500) NULL,
  poster_path VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_videos_active_order (active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS about_artworks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NULL,
  image_path VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_about_artworks_active_order (active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS gallery (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  image_path VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_areas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  area_name VARCHAR(180) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(220) NOT NULL,
  url VARCHAR(500) NOT NULL DEFAULT '#',
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(150) NULL,
  phone VARCHAR(80) NULL,
  email VARCHAR(180) NULL,
  address VARCHAR(255) NULL,
  service_needed VARCHAR(150) NULL,
  desired_date DATE NULL,
  message TEXT NULL,
  photo_path VARCHAR(500) NULL,
  status ENUM('new','contacted','in_progress','won','lost','closed') NOT NULL DEFAULT 'new',
  assigned_to INT UNSIGNED NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  follow_up_date DATE NULL,
  internal_notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_estimate_status_created(status,created_at), KEY idx_estimate_assigned(assigned_to,status,follow_up_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  estimate_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NULL,
  mime_type VARCHAR(100) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_estimate_attachment_estimate (estimate_id),
  CONSTRAINT fk_estimate_attachment_request FOREIGN KEY (estimate_id) REFERENCES estimate_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS public_form_rate_limits (
  action_key VARCHAR(40) NOT NULL,
  identity_hash CHAR(64) NOT NULL,
  window_started_at DATETIME NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  blocked_until DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (action_key, identity_hash),
  KEY idx_public_form_rate_updated (updated_at),
  KEY idx_public_form_rate_blocked (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS correo_tipo (
  correo_tipo_id INT NOT NULL,
  nombre VARCHAR(30) NOT NULL,
  PRIMARY KEY (correo_tipo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS correo (
  correo_id INT NOT NULL AUTO_INCREMENT COMMENT 'Identificador unico de la configuracion de correo',
  correo_tipo_id INT NOT NULL COMMENT 'Tipo de correo',
  metodo_envio ENUM('SMTP','GRAPH') NOT NULL DEFAULT 'SMTP' COMMENT 'SMTP o Microsoft Graph',
  server VARCHAR(150) NOT NULL DEFAULT '' COMMENT 'Servidor SMTP o graph.microsoft.com',
  correo VARCHAR(180) NOT NULL COMMENT 'Correo emisor',
  password TEXT NULL COMMENT 'Contrasena SMTP cifrada',
  port INT NOT NULL DEFAULT 587 COMMENT 'Puerto SMTP; Graph usa 0',
  smtp_secure VARCHAR(10) NOT NULL DEFAULT 'tls' COMMENT 'tls o ssl',
  tenant_id VARCHAR(150) DEFAULT NULL,
  client_id VARCHAR(150) DEFAULT NULL,
  client_secret TEXT NULL COMMENT 'Client secret cifrado',
  graph_user VARCHAR(180) DEFAULT NULL,
  save_to_sent_items TINYINT(1) NOT NULL DEFAULT 1,
  estado TINYINT NOT NULL DEFAULT 1 COMMENT '1 Activo, 2 Inactivo',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (correo_id),
  KEY idx_correo_tipo_estado (correo_tipo_id,estado),
  CONSTRAINT fk_correo_tipo FOREIGN KEY (correo_tipo_id) REFERENCES correo_tipo(correo_tipo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_integrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_name VARCHAR(120) NOT NULL,
  api_type VARCHAR(40) NOT NULL DEFAULT 'custom',
  category VARCHAR(80) NOT NULL DEFAULT 'General',
  environment ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox',
  auth_type VARCHAR(40) NOT NULL DEFAULT 'api_key',
  base_url VARCHAR(500) NULL,
  public_key VARCHAR(500) NULL,
  secret_key TEXT NULL,
  webhook_secret TEXT NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO correo_tipo (correo_tipo_id,nombre) VALUES
(1,'Website Alerts'),(2,'Admin Security'),(3,'Estimate Requests'),(4,'Auto Replies')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

INSERT INTO site_content(content_key,content_value) VALUES
('hero_eyebrow','PAINTING · REPAIRS · MAINTENANCE'),
('hero_title','More Than Repairs and Maintenance. We Care About Your Home.'),
('hero_text','Your home is where you share meaningful moments with your family, rest after a long day, and build lasting memories. At Castro’s Ready, we work to ensure every space reflects comfort, safety, and pride through reliable repairs, maintenance, and home improvement services.'),
('intro_title','Professional home improvement services you can trust.'),
('intro_text','Professional painting, remodeling, flooring, decks, carpentry, concrete, drywall, and pressure washing services you can trust.'),
('about_title','Built to care for the places that matter most.'),
('about_text','Castro’s Ready is focused on dependable home improvement work, thoughtful service, and quality results for every project.'),
('about_text_2','We believe each home deserves to be a safe, functional, and pleasant space for the people who live there.'),
('mission','Provide painting, remodeling, and maintenance services with high quality standards, building trust and satisfaction with every client.'),
('vision','Be a company recognized for transforming homes through honest, professional, and excellent work.'),
('areas_title','Quality work, wherever your project is.'),
('areas_text','Our confirmed service cities and coverage areas can be managed from the administrator panel.'),
('estimate_title','Tell us about your project.'),
('estimate_text','Request a free estimate. Fill in all or only the information you have available; attaching photos is optional.'),
('contact_title','Ready when your home needs us.')
ON DUPLICATE KEY UPDATE content_value=VALUES(content_value);

INSERT INTO settings(setting_key,setting_value) VALUES
('phone','+1 (202) 644-2717'),
('phone_digits','12026442717'),
('email','castrosreadycompany@gmail.com'),
('estimate_notification_email','castrosreadycompany@gmail.com'),
('estimate_copy_email',''),
('youtube','https://www.youtube.com/@CastrosReady'),
('facebook','#'),('tiktok','#'),('website','castrosready.us'),('business_hours',''),
('admin_brand_name','Castro''s Ready Admin'),('admin_logo_path','assets/logo.jpg'),('favicon_path','assets/logo.jpg'),
('maintenance_mode','0'),('maintenance_title','We are improving our website.'),('maintenance_text','Castro''s Ready will be back shortly. For immediate assistance, contact us by phone or WhatsApp.'),('maintenance_image_path',''),
('whatsapp_enabled','1'),('whatsapp_message','Hello, I would like more information about Castro''s Ready services.'),('whatsapp_position','right'),
('service_map_query','6624 Aspern Drive, Elkridge, MD 21075, United States'),
('service_map_label','Castro''s Ready'),
('service_map_enabled','1')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

INSERT INTO services(title,details,sort_order,active)
SELECT 'Painting','Interior Painting · Exterior Painting · Cabinet Painting · Ceiling Painting · Trim & Doors Painting',10,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Painting');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Remodeling','Bathroom Remodeling · Kitchen Remodeling · Basement Remodeling',20,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Remodeling');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Flooring','Luxury Vinyl Plank (LVP) · Laminate Flooring · Hardwood Flooring · Floor Repair',30,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Flooring');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Decks & Outdoor Living','Deck Construction · Deck Repair · Staining & Sealing',40,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Decks & Outdoor Living');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Siding & Carpentry','Siding Installation · Wood Repair · Doors & Windows',50,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Siding & Carpentry');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Drywall Services','Installation · Repairs · Water Damage Repair · Texture Matching',60,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Drywall Services');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Concrete Services','Patios · Sidewalks · Slabs · Repairs',70,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Concrete Services');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Pressure Washing','Houses · Decks · Driveways · Patios · Fences',80,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Pressure Washing');


UPDATE services SET icon_path='assets/service-badges/painting.png' WHERE title='Painting';
UPDATE services SET icon_path='assets/service-badges/remodeling.png' WHERE title='Remodeling';
UPDATE services SET icon_path='assets/service-badges/flooring.png' WHERE title='Flooring';
UPDATE services SET icon_path='assets/service-badges/decks-outdoor-living.png' WHERE title='Decks & Outdoor Living';
UPDATE services SET icon_path='assets/service-badges/siding-carpentry.png' WHERE title='Siding & Carpentry';
UPDATE services SET icon_path='assets/service-badges/drywall-services.png' WHERE title='Drywall Services';
UPDATE services SET icon_path='assets/service-badges/concrete-services.png' WHERE title='Concrete Services';
UPDATE services SET icon_path='assets/service-badges/pressure-washing.png' WHERE title='Pressure Washing';

INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Before & After','',10,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Before & After');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Interior Painting','',20,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Interior Painting');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Exterior Painting','',30,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Exterior Painting');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Remodeling','',40,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Remodeling');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Flooring','',50,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Flooring');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Drywall Repair','',60,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Drywall Repair');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Decks','',70,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Decks');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Concrete','',80,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Concrete');

INSERT INTO tips(title,url,sort_order,active)
SELECT 'How Often Should You Paint Your Home?','#',10,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='How Often Should You Paint Your Home?');
INSERT INTO tips(title,url,sort_order,active)
SELECT 'Signs Your Home Needs Maintenance','#',20,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='Signs Your Home Needs Maintenance');
INSERT INTO tips(title,url,sort_order,active)
SELECT 'Best Paint Colors for Modern Homes','#',30,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='Best Paint Colors for Modern Homes');
INSERT INTO tips(title,url,sort_order,active)
SELECT 'How to Protect Your Deck Year-Round','#',40,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='How to Protect Your Deck Year-Round');
INSERT INTO tips(title,url,sort_order,active)
SELECT 'Top Remodeling Trends','#',50,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='Top Remodeling Trends');


CREATE TABLE IF NOT EXISTS content_drafts (
  content_key VARCHAR(100) NOT NULL,
  content_value TEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (content_key), KEY idx_content_drafts_user (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS content_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  snapshot_json LONGTEXT NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_content_versions_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS site_sections (
  section_key VARCHAR(60) NOT NULL,
  label VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (section_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS media_library (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_media_path (file_path), KEY idx_media_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS activity_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  action_type VARCHAR(80) NOT NULL,
  description VARCHAR(500) NOT NULL,
  metadata_json TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_activity_created (created_at), KEY idx_activity_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS admin_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  notification_type VARCHAR(40) NOT NULL DEFAULT 'info',
  title VARCHAR(180) NOT NULL,
  message VARCHAR(500) NOT NULL,
  action_url VARCHAR(500) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_notifications_read_created (is_read,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS site_backups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  backup_name VARCHAR(180) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_backups_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO site_sections(section_key,label,sort_order,active) VALUES
('home','Home / Hero',10,1),('intro','What We Do',20,1),('about','About Us',30,1),('services','Services',40,1),('videos','Videos',50,1),('gallery','Gallery',60,1),('areas','Service Areas',70,1),('estimate','Free Estimate',80,1),('tips','Home Tips',90,1),('contact','Contact',100,1)
ON DUPLICATE KEY UPDATE label=VALUES(label);
INSERT INTO about_artworks(title,image_path,sort_order,active)
SELECT 'Mission','assets/about/mission.png',10,1
WHERE NOT EXISTS (SELECT 1 FROM about_artworks WHERE image_path='assets/about/mission.png');
INSERT INTO about_artworks(title,image_path,sort_order,active)
SELECT 'Vision','assets/about/vision.png',20,1
WHERE NOT EXISTS (SELECT 1 FROM about_artworks WHERE image_path='assets/about/vision.png');

INSERT INTO videos(title,description,video_type,video_url,file_path,poster_path,sort_order,active)
SELECT 'Project Showcase','', 'youtube','https://youtu.be/01nlW2CAMgA?si=2RbdC7qcg1i3etdD',NULL,NULL,10,1
WHERE NOT EXISTS (SELECT 1 FROM videos WHERE video_url LIKE '%01nlW2CAMgA%');
INSERT INTO videos(title,description,video_type,video_url,file_path,poster_path,sort_order,active)
SELECT 'Project Highlight','', 'youtube','https://youtube.com/shorts/rIuzhI1PAL0?si=tn8Ny8yd7EpI2AmF',NULL,NULL,20,1
WHERE NOT EXISTS (SELECT 1 FROM videos WHERE video_url LIKE '%rIuzhI1PAL0%');

INSERT INTO settings(setting_key,setting_value) VALUES
('seo_title','Castro''s Ready | Home Improvement'),
('seo_description','Castro''s Ready — professional painting, remodeling, flooring, decks, carpentry, concrete, drywall and pressure washing.'),
('seo_social_image',''),('seo_robots','index,follow'),
('mission_artwork_path','assets/about/mission.png'),('vision_artwork_path','assets/about/vision.png'),
('developer_credit_enabled','0'),('developer_credit_text','Website by ES MULTISERVICIOS')
ON DUPLICATE KEY UPDATE setting_value=setting_value;



CREATE TABLE IF NOT EXISTS admin_roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_key VARCHAR(60) NOT NULL,
  role_name VARCHAR(120) NOT NULL,
  description VARCHAR(300) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_admin_role_key(role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_permissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  permission_key VARCHAR(100) NOT NULL,
  permission_name VARCHAR(160) NOT NULL,
  permission_group VARCHAR(80) NOT NULL DEFAULT 'General',
  PRIMARY KEY(id), UNIQUE KEY uq_admin_permission_key(permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY(role_id,permission_id), KEY idx_role_permission_permission(permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_approvals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submitted_by INT UNSIGNED NOT NULL,
  status ENUM('pending','approved','changes_requested','cancelled') NOT NULL DEFAULT 'pending',
  note TEXT NULL,
  reviewer_note TEXT NULL,
  reviewed_by INT UNSIGNED NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  PRIMARY KEY(id), KEY idx_content_approval_status(status,submitted_at), KEY idx_content_approval_submitter(submitted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  estimate_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id), KEY idx_estimate_notes_request(estimate_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  session_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  last_seen_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  PRIMARY KEY(id), UNIQUE KEY uq_admin_session_hash(session_hash), KEY idx_admin_sessions_user(admin_id,last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_login_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  username_attempt VARCHAR(80) NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id), KEY idx_login_events_user(admin_id,created_at), KEY idx_login_events_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_notification_reads (
  notification_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NOT NULL,
  read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(notification_id,admin_id), KEY idx_notification_reads_admin(admin_id,read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_roles(role_key,role_name,description,is_system,active) VALUES
('owner','Owner','Full protected ownership of the website.',1,1),
('administrator','Administrator','Full day-to-day administration except protected owner controls.',1,1),
('editor','Editor','Creates and edits website content; publishing requires approval.',1,1),
('sales','Sales','Works with estimate requests and customer follow-ups.',1,1),
('viewer','Viewer','Read-only operational access.',1,1)
ON DUPLICATE KEY UPDATE role_name=VALUES(role_name),description=VALUES(description),active=1;

INSERT INTO admin_permissions(permission_key,permission_name,permission_group) VALUES
('dashboard.view','View dashboard','General'),
('content.view','View page content','Content'),
('content.edit','Edit drafts','Content'),
('content.publish','Publish content','Content'),
('content.approve','Approve submitted content','Content'),
('sections.manage','Manage landing sections','Content'),
('media.manage','Manage Media Library','Content'),
('services.manage','Manage services','Content'),
('gallery.manage','Manage gallery','Content'),
('videos.manage','Manage website videos','Content'),
('areas.manage','Manage service areas','Content'),
('tips.manage','Manage home tips','Content'),
('estimates.view','View estimate requests','Business'),
('estimates.manage_assigned','Manage assigned estimates','Business'),
('estimates.manage_all','Manage all estimates and assignments','Business'),
('email.manage','Manage email configuration','System'),
('integrations.manage','Manage integrations & APIs','System'),
('seo.manage','Manage SEO','Content'),
('health.view','View Website Health','General'),
('notifications.view','View notifications','General'),
('activity.view','View activity log','General'),
('backups.manage','Manage backups','System'),
('settings.manage','Manage site settings','System'),
('users.manage','Manage administrator users','Administration'),
('roles.manage','Manage roles & permissions','Administration'),
('security.manage','Manage active sessions and security','Administration')
ON DUPLICATE KEY UPDATE permission_name=VALUES(permission_name),permission_group=VALUES(permission_group);

INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.edit' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.publish' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.approve' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='sections.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='media.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='services.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='gallery.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='videos.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='areas.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='tips.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.manage_assigned' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.manage_all' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='email.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='integrations.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='seo.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='health.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='activity.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='backups.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='settings.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='users.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='security.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.edit' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='sections.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='media.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='services.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='gallery.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='videos.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='areas.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='tips.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='seo.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='health.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.view' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.manage_assigned' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='health.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r CROSS JOIN admin_permissions p WHERE r.role_key='owner';

SET FOREIGN_KEY_CHECKS=1;
