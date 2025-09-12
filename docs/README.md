# 🚖 Trip Management Challenge

## 📦 Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/trip-management.git
   cd trip-management
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Copy and configure environment file**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   - Update your `.env` with database credentials.

4. **Run migrations & seeders**
   ```bash
   php artisan migrate --seed
   ```

5. **Start development server**
   ```bash
   php artisan serve
   ```

---

## 🧩 Key Design Decisions

### 1. Overlapping Trip Validation
- Business logic requires **drivers and vehicles cannot be double-booked**.
- Validation runs inside the **Eloquent model observer** (`TripObserver::creating` / `TripObserver::updating`) to ensure consistency across UI, API, and seeding.
- Overlap check uses this query:

```php
$query = Trip::where('id', '!=', $this->id)
    ->where(function ($q) {
        $q->where('driver_id', $this->driver_id)
          ->orWhere('vehicle_id', $this->vehicle_id);
    })
    ->where('status', '!=', TripStatusEnum::CANCELLED->value)
    ->where(function ($q) {
        $q->whereBetween('schedule_start', [$this->schedule_start, $this->schedule_end])
          ->orWhereBetween('schedule_end', [$this->schedule_start, $this->schedule_end])
          ->orWhere(function ($subQ) {
              $subQ->where('schedule_start', '<=', $this->schedule_start)
                   ->where('schedule_end', '>=', $this->schedule_end);
          });
    });
```

- The **third condition (subquery)** is required to catch cases where an existing trip fully contains the new trip’s window.
- This ensures *all overlap cases* are covered.

✅ **Performance considerations**
- Indexed columns: `driver_id`, `vehicle_id`, `schedule_start`, `schedule_end`.
- The overlap check is selective (driver/vehicle filter first), so queries scale with indexed lookups rather than scanning the entire trips table.

---

### 2. Duration Formatting
- Implemented as a **model accessor** (`getDurationLabelAttribute`) instead of inline logic in Filament.
- Keeps UI clean and allows reusability in exports, APIs, etc.
- Format example: `2h 30m`, `45m`.

---

### 3. Validation Layer
- Used **Form validation** (Filament) for user input errors (end before start).
- Used **Observer validation** for business logic (overlapping trips).
- This separation keeps UI validation fast while ensuring system-wide consistency.

---

## 🤔 Assumptions
- A trip is only considered conflicting if **status ≠ CANCELLED**.
- `schedule_start` and `schedule_end` are always stored as **UTC datetimes**.
- Only **drivers** and **vehicles** matter for overlap checks; passengers are not part of conflict validation.
- Trip duration is always derived from schedule times (not actual start/end).

---

## 🧪 Running Tests
Feature and unit tests cover:
- Trip creation with valid schedules.
- Preventing overlaps.
- Duration formatting.

Run tests:
```bash
php artisan test
```
