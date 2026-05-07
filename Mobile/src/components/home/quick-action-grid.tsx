import MaterialIcons from "@expo/vector-icons/MaterialIcons";
import { useRouter } from "expo-router";
import { Pressable, StyleSheet, Text, View } from "react-native";

import { QuickAction } from "@/src/constants/home-data";

type QuickActionGridProps = {
  actions: QuickAction[];
};

export function QuickActionGrid({ actions }: QuickActionGridProps) {
  const router = useRouter();

  const handlePress = (id: string) => {
    if (id === "qa-4") {
      router.push("/chat");
    }
  };

  return (
    <View style={styles.grid}>
      {actions.map((action) => (
        <Pressable
          key={action.id}
          style={[styles.card, { backgroundColor: action.bgColor }]}
          onPress={() => handlePress(action.id)}
        >
          <MaterialIcons
            name={action.icon as any}
            size={28}
            color={action.color}
          />
          <Text style={[styles.title, { color: action.color }]}>
            {action.title}
          </Text>
          <Text style={styles.subtitle}>{action.subtitle}</Text>
        </Pressable>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  grid: {
    flexDirection: "row",
    flexWrap: "wrap",
    justifyContent: "space-between",
    paddingHorizontal: 20,
    gap: 12,
  },
  card: {
    width: "48%",
    borderRadius: 16,
    padding: 16,
    gap: 8,
    minHeight: 120,
    justifyContent: "center",
  },
  title: {
    fontSize: 12,
    fontWeight: "700",
    marginTop: 4,
  },
  subtitle: {
    fontSize: 12,
    color: "#64748b",
  },
});
