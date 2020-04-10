CREATE TABLE payments (
    id TEXT PRIMARY KEY NOT NULL,
    created_at datetime NOT NULL,
    type TEXT NOT NULL,
    invoice_number TEXT,
    completed_at datetime,
    email TEXT NOT NULL,
    amount INTEGER NOT NULL,
    address_first_name TEXT NOT NULL,
    address_last_name TEXT NOT NULL,
    address_address1 TEXT NOT NULL,
    address_postcode TEXT NOT NULL,
    address_city TEXT NOT NULL,
    payment_intent_id TEXT,
    username TEXT,
    frequency TEXT
);