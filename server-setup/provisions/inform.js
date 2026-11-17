// Smart Inform Provision for GenieACS
// Automatically discovers and populates parameters from Huawei, ZTE, Fiberhome, XPON, etc.

const now = Date.now();

// 1. Enforce Periodic Inform Interval to 300 seconds (5 minutes)
declare('InternetGatewayDevice.ManagementServer.PeriodicInformInterval', {value: now}, {value: 300});
declare('Device.ManagementServer.PeriodicInformInterval', {value: now}, {value: 300});

// 2. Fetch WAN IP Address and PPPoE Username
declare('InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.ExternalIPAddress', {value: now});
declare('InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.Username', {value: now});
declare('InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*.ExternalIPAddress', {value: now});

// 3. Fetch Wi-Fi SSID
declare('InternetGatewayDevice.LANDevice.*.WLANConfiguration.*.SSID', {value: now});
declare('Device.WiFi.SSID.*.SSID', {value: now});

// 4. Fetch Optical Rx/Tx Power & Temperature
declare('InternetGatewayDevice.WANDevice.*.WANGPONInterfaceConfig.RXPower', {value: now});
declare('InternetGatewayDevice.WANDevice.*.WANGPONInterfaceConfig.TXPower', {value: now});
declare('Device.Optical.Interface.*.OpticalSignalLevel', {value: now});

// 5. Fetch Device Uptime & Status
declare('InternetGatewayDevice.DeviceInfo.UpTime', {value: now});
declare('Device.DeviceInfo.UpTime', {value: now});

