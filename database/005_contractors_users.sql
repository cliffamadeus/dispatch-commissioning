-- Link contractors to user accounts
ALTER TABLE contractors
ADD COLUMN user_id INT NULL AFTER contractor_id,
ADD CONSTRAINT fk_contractor_user
    FOREIGN KEY (user_id)
    REFERENCES users(user_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL;