-- #1 contractors table
CREATE TABLE IF NOT EXISTS contractors (
    -- Primary key for the contractors table
    contractor_id INT PRIMARY KEY AUTO_INCREMENT,

    -- Contractor name
    contractor_first_name VARCHAR(50) NOT NULL,
    contractor_last_name VARCHAR(50) NOT NULL,

    -- Contractor specialty
    contractor_specialty VARCHAR(50) NOT NULL,

    -- Contractor created at timestamp
    contractor_created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- Insert statement #1: Insert Contractors
INSERT INTO contractors (
    contractor_first_name,
    contractor_last_name,
    contractor_specialty
) VALUES
('CLIFF AMADEUS', 'EVANGELIO', 'BSIT'),
('JAN XAVIER', 'EVANGELIO', 'BSA-AGRONOMY'),
('RON EDMUND', 'EVANGELIO', 'BSEE'),
('RK', 'FERNANDEZ', 'BSIT');

-- #2 projects table
CREATE TABLE IF NOT EXISTS projects (
    -- Primary key for the projects table
    project_id INT PRIMARY KEY AUTO_INCREMENT,

    -- Project details
    project_title VARCHAR(100) NOT NULL,
    project_manager VARCHAR(100) NOT NULL,
    project_category VARCHAR(50) NOT NULL,

    -- Project created at timestamp
    project_created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;

-- Insert statement #2: Insert Projects
INSERT INTO projects (
    project_title,
    project_manager,
    project_category
) VALUES
('Skyline Tower Construction', 'Miguel Santos', 'Commercial Building'),
('Riverside Bridge Rehabilitation', 'Angela Reyes', 'Infrastructure'),
('Greenfield Residential Complex', 'David Cruz', 'Residential Development');

-- #3 project_updates table
CREATE TABLE IF NOT EXISTS project_updates (
    -- Primary key for the project updates table
    update_id INT AUTO_INCREMENT PRIMARY KEY,

    -- Foreign key references
    contractor_id INT NOT NULL,
    project_id INT NOT NULL,

    -- Update timestamp not null by default
    update_date TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    -- Update completion timestamp null by default
    update_completion_date TIMESTAMP NULL
        DEFAULT NULL,

    -- Project updates table constraints and foreign keys
    CONSTRAINT fk_update_contractor
        FOREIGN KEY (contractor_id)
        REFERENCES contractors(contractor_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_update_project
        FOREIGN KEY (project_id)
        REFERENCES projects(project_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_general_ci;


-- Update Due Date
ALTER TABLE project_updates
ADD COLUMN update_due_date DATE NULL
AFTER update_date;

-- Sample Due Date 
INSERT INTO project_updates (
    contractor_id,
    project_id,
    update_date,
    update_due_date
) VALUES
(1, 1, '2026-09-15', '2026-09-20'),
(2, 2, '2026-09-16', '2026-09-20'),
(3, 3, '2026-09-17', '2026-09-20');