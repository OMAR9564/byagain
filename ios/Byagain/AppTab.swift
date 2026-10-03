import Foundation

enum AppTab: String, CaseIterable, Identifiable {
	case today, library, mix, streak

	var id: String {
		rawValue
	}

	var title: String {
		switch self {
		case .today:
			"Today"
		case .library:
			"Library"
		case .mix:
			"Mix"
		case .streak:
			"Streak"
		}
	}

	var systemImage: String {
		switch self {
		case .today:
			"text.alignleft"
		case .library:
			"books.vertical"
		case .mix:
			"shuffle"
		case .streak:
			"flame"
		}
	}

	var path: String {
		switch self {
		case .today:
			"/review"
		case .library:
			"/library"
		case .mix:
			"/mix"
		case .streak:
			"/streak"
		}
	}
}
