-- Run once against an existing CampusFix database to store technician completion evidence.
ALTER TABLE requests
    ADD COLUMN completion_photo VARCHAR(255) NULL AFTER photo;
