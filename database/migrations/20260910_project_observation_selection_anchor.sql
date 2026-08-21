ALTER TABLE project_observations
    ADD COLUMN IF NOT EXISTS selection_anchor LONGTEXT
        CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL
        CHECK (selection_anchor IS NULL OR JSON_VALID(selection_anchor))
        AFTER location_reference;
