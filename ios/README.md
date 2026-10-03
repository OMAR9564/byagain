# Byagain iOS App

A native iOS shell around the byagain web app. It loads the live site in a web view, keeps you signed in, and keeps offline review working. It is meant to be installed straight from Xcode onto your own iPhone, without the App Store.

## What's native

The app shell provides a native tab bar with four tabs (Today, Library, Mix, Streak) that each load their own view of the site. Tapping a tab again pops to its root. You can swipe back at the left edge of any tab, pull to refresh, and native dialogs appear for alerts and confirmations. Links to Add source and New source open as sheets instead of full screens. Downloads go to the share sheet for saving or sharing.

## Requirements

- Mac with a macOS version that runs Xcode 16 or later
- Xcode 16 or later
- iPhone with iOS 16 or later
- USB cable (for first installation)
- Free Apple ID (no App Store account needed)

## Installation Steps

1. **Open the project**
   - Open `ios/Byagain.xcodeproj` in Xcode

2. **Configure signing**
   - Select the Byagain target
   - Go to Signing & Capabilities
   - Check "Automatically manage signing"
   - Choose your Team from the dropdown
   - If you don't have an Apple ID configured:
     - Go to Xcode > Settings > Accounts
     - Click "+" and add your Apple ID
     - Return to Signing & Capabilities and select your team

3. **Handle bundle ID conflicts (if needed)**
   - If you get an error that the bundle ID `com.omaralfarouk.byagain` is already in use:
     - In Signing & Capabilities, change the Product Bundle Identifier to something unique
     - Example: `com.omaralfarouk.byagain.myname`

4. **Prepare your device**
   - Plug in your iPhone via USB and trust the computer
   - On the iPhone, go to Settings > Privacy & Security > Developer Mode
   - Toggle Developer Mode on (requires restart)
   - Developer Mode only appears after Xcode has seen the phone once; if it is missing, plug in and select the phone in Xcode first

5. **Build and run**
   - Select your iPhone as the run destination
   - Press Cmd+R to build and install
   - On first launch with a free Apple ID:
     - Go to Settings > General > VPN & Device Management
     - Find your developer certificate and tap Trust
   - Sign in and tick **Remember me**, so the session survives the app being closed

## Important Notes

### Free Apple ID Signatures
- Certificates signed with a free Apple ID expire after 7 days
- Simply connect and run from Xcode again to refresh
- All your app data is preserved across refreshes
- A free Apple ID can have at most three sideloaded apps installed at once

### Paid Developer Program
- If you join Apple Developer Program (annual subscription):
  - Certificates last 1 year
  - Can distribute via TestFlight or App Store

### Web Push Notifications
- Push notifications do not work inside WKWebView
- Email reminders still work normally
- This is a platform limitation of Safari-powered web views

### Live Updates
- The app always shows the live website at https://byagain.omaralfarouk.com
- Web deploys update immediately in the app
- You only need to rebuild the app if files in the `ios/` directory change

### Offline Review
- Offline review works after the app has been opened online at least once
- Service workers are enabled via app-bound domains
- The offline review state persists across app sessions

### Wireless Debugging (Optional)
- After your first cabled run, you can debug over WiFi:
  - In Xcode, go to Window > Devices and Simulators
  - Right-click your connected device
  - Select "Connect via network"
