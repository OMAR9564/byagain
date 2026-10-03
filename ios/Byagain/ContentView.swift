import SwiftUI

struct ContentView: View {
	@StateObject private var viewModel = WebViewModel()

	var body: some View {
		ZStack {
			Color("Canvas")
				.ignoresSafeArea()

			WebView(viewModel: viewModel)
				.ignoresSafeArea(edges: .bottom)

			if viewModel.loadFailed {
				VStack(spacing: 16) {
					Text("You're offline")
						.font(.headline)
					Text("byagain couldn't reach the server.")
						.font(.body)
						.foregroundColor(.secondary)
					Button(action: {
						viewModel.reload()
					}) {
						Text("Try again")
							.fontWeight(.semibold)
							.frame(maxWidth: .infinity)
							.padding()
							.background(Color("AccentColor"))
							.foregroundColor(.white)
							.cornerRadius(8)
					}
					.padding()
				}
				.padding()
				.background(Color("Canvas"))
				.cornerRadius(12)
				.padding()
			}
		}
	}
}

#Preview {
	ContentView()
}
