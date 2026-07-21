package textutil_test

import (
	"testing"

	"finba.se/geo/internal/textutil"
)

func TestNormalizeSearch(t *testing.T) {
	t.Parallel()
	cases := []struct {
		in, want string
	}{
		{"Tramandaí", "tramandai"},
		{"São Paulo", "sao paulo"},
		{"Córdoba", "cordoba"},
		{"München", "munchen"},
		{"Québec", "quebec"},
		{"CÓRDOBA", "cordoba"},
		{"  São   Paulo ", "sao paulo"},
		{"", ""},
		{"   ", ""},
		{"a\u0301", "a"}, // precomposed vs combining: á as a + combining acute
		{"Rio-Grande", "rio-grande"},
		{"O'Higgins", "o'higgins"},
		{"District 9", "district 9"},
		{"São\t\tPaulo", "sao paulo"},
	}
	for _, tc := range cases {
		t.Run(tc.in+"_"+tc.want, func(t *testing.T) {
			t.Parallel()
			got := textutil.NormalizeSearch(tc.in)
			if got != tc.want {
				t.Fatalf("NormalizeSearch(%q)=%q want %q", tc.in, got, tc.want)
			}
		})
	}
}
