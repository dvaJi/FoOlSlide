// Imports
import { GraphQLObjectType, GraphQLString, GraphQLInt } from "graphql";

// App Imports
import { WorkType } from "../works/types";

// WorksGenre type
const WorksGenreType = new GraphQLObjectType({
  name: "worksGenre",
  description: "WorksGenre Type",

  fields: () => ({
    id: { type: GraphQLInt },
    work: { type: WorkType },
    language: { type: GraphQLString },
    description: { type: GraphQLString },
    createdAt: { type: GraphQLString },
    updatedAt: { type: GraphQLString }
  })
});

// Genres type
const GenresType = new GraphQLObjectType({
  name: "genresType",
  description: "Genres Type",

  fields: () => ({
    id: { type: GraphQLInt },
    name: { type: GraphQLString }
  })
});

// Demographic type
const DemographicType = new GraphQLObjectType({
  name: "demographicType",
  description: "Demographic Type",

  fields: () => ({
    id: { type: GraphQLInt },
    name: { type: GraphQLString }
  })
});

export { WorksGenreType, GenresType, DemographicType };
