import SwiftUI
import UIKit

@main
struct ByagainApp: App {
	init() {
		let appearance = UITabBarAppearance()
		appearance.configureWithDefaultBackground()
		UITabBar.appearance().standardAppearance = appearance
		UITabBar.appearance().scrollEdgeAppearance = appearance
	}

	var body: some Scene {
		WindowGroup {
			RootView()
		}
	}
}
