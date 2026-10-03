import Foundation

enum AppConfig {
	static let host = "byagain.omaralfarouk.com"
	static let baseURL = URL(string: "https://byagain.omaralfarouk.com")!

	static func url(_ path: String) -> URL {
		baseURL.appendingPathComponent(path)
	}

	static let sheetPaths: Set<String> = ["/add", "/library/sources/create"]
	static let guestPathPrefixes = ["/login", "/register", "/forgot-password", "/reset-password", "/verify-email", "/confirm-password"]
}
