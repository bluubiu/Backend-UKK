# 1. Create Temp Admin
$adminSetup = "try { App\Models\User::where('username', 'tempadmin')->forceDelete(); } catch(\Exception $e) {} " +
              "echo json_encode(App\Models\User::create([" +
              "'username' => 'tempadmin', " +
              "'password' => Hash::make('password'), " +
              "'full_name' => 'Temp Admin', " +
              "'email' => 'tempadmin@example.com', " +
              "'role_id' => 1, " +
              "'is_active' => 1, " + 
              "'score' => 100" +
              "]));"
$adminUserJson = php artisan tinker --execute="$adminSetup"
Write-Host "Admin Created: $adminUserJson"

# 2. Login
$loginBody = @{ username = "tempadmin"; password = "password" } | ConvertTo-Json
$loginResponse = Invoke-RestMethod -Uri "http://localhost:8000/api/login" -Method Post -Body $loginBody -ContentType "application/json"
$token = $loginResponse.token
Write-Host "Token Acquired"
$headers = @{ "Authorization" = "Bearer $token"; "Content-Type" = "application/json"; "Accept" = "application/json" }

# 3. Test Create User (Weak Password)
$weakBody = @{
    username = "testuser_weak_admin"
    password = "weak"
    password_confirmation = "weak"
    full_name = "Test Weak Admin"
    email = "weakadmin@example.com"
    role_id = 3
    is_active = $true
} | ConvertTo-Json
try {
    Invoke-RestMethod -Uri "http://localhost:8000/api/users" -Method Post -Body $weakBody -Headers $headers
    Write-Host "FAILURE: Weak password accepted (Create)!"
} catch {
    Write-Host "SUCCESS: Weak password rejected (Create). Status: $($_.Exception.Response.StatusCode.value__)"
}

# 4. Test Create User (Mismatch)
$mismatchBody = @{
    username = "testuser_mismatch_admin"
    password = "StrongPassword1!"
    password_confirmation = "Mismatch1!"
    full_name = "Test Mismatch Admin"
    email = "mismatchadmin@example.com"
    role_id = 3
    is_active = $true
} | ConvertTo-Json
try {
    Invoke-RestMethod -Uri "http://localhost:8000/api/users" -Method Post -Body $mismatchBody -Headers $headers
    Write-Host "FAILURE: Mismatch password accepted (Create)!"
} catch {
    Write-Host "SUCCESS: Mismatch password rejected (Create). Status: $($_.Exception.Response.StatusCode.value__)"
}

# 5. Test Create User (Strong)
$strongBody = @{
    username = "testuser_strong_admin"
    password = "StrongPassword1!"
    password_confirmation = "StrongPassword1!"
    full_name = "Test Strong Admin"
    email = "strongadmin@example.com"
    role_id = 3
    is_active = $true
} | ConvertTo-Json
try {
    $user = Invoke-RestMethod -Uri "http://localhost:8000/api/users" -Method Post -Body $strongBody -Headers $headers
    Write-Host "SUCCESS: Strong user created. ID: $($user.id)"
    
    # 6. Test Update User (Weak Password)
    $updateWeakBody = @{
        password = "weak"
        password_confirmation = "weak"
        _method = "PUT"
    } | ConvertTo-Json
    try {
        Invoke-RestMethod -Uri "http://localhost:8000/api/users/$($user.id)" -Method Post -Body $updateWeakBody -Headers $headers
        Write-Host "FAILURE: Weak password accepted (Update)!"
    } catch {
        Write-Host "SUCCESS: Weak password rejected (Update)."
    }

} catch {
    Write-Host "FAILURE: Strong user creation failed! $($_.Exception.Message)"
    $stream = $_.Exception.Response.GetResponseStream()
    $reader = New-Object System.IO.StreamReader($stream)
    Write-Host "Details: $($reader.ReadToEnd())"
}
